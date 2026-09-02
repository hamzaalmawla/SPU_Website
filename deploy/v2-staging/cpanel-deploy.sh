#!/usr/bin/env bash
#
# Deployment task for cPanel Git Version Control on v2.spu.edu.sy.
#
# cPanel clones the repository on the server and runs the tasks in .cpanel.yml
# from that clone. This is the auditable, shell-free deployment mechanism REM-07
# asks for: the executor is cPanel, the input is a commit, and the output is this
# script's log.
#
# public/build is committed and ships with the clone, so the deploy does not
# depend on Node being installed here - see .gitignore for why. Rebuild it before
# a release with `php artisan view:clear && npm run build` and commit the result.
#
# The deployment this replaces ran `npm ci && npm run build` on the host. That
# works if Node is present; committing the build removes the dependency either
# way, and makes what shipped identical to what was tested.
#
# vendor/ is not committed; composer runs here.
#
# Everything is idempotent and safe to re-run.

set -euo pipefail

APP="${SPU_APP_PATH:-/home/spuedu/spu_v2_app}"
WEB="${SPU_WEB_PATH:-/home/spuedu/public_html/spu_v2/public}"
PHP="${SPU_PHP_BIN:-/opt/cpanel/ea-php84/root/usr/bin/php}"
COMPOSER="${SPU_COMPOSER:-/home/spuedu/.spu_v2_tools/composer.phar}"
SOURCE="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

log()  { printf '\n▸ %s\n' "$1"; }
fail() { printf '\n✗ %s\n' "$1" >&2; exit 1; }

log "Deploying ${SOURCE} → ${APP}"

# ── Preconditions ────────────────────────────────────────────────────────────
# Each of these has taken the site down before. Check them while it is still
# cheap to stop.
[[ -x "${PHP}" ]]        || fail "PHP binary not found at ${PHP}"
[[ -d "${APP}" ]]        || fail "Application directory ${APP} does not exist"
[[ -f "${APP}/.env" ]]   || fail "${APP}/.env is missing. It is not in git and must never be."
[[ -d "${WEB}" ]]        || fail "Web root ${WEB} does not exist"

# The docroot is NOT synced by this script - only public/build/ and the SVG
# assets are copied into it. public/.htaccess and public/.user.ini there are
# hand-maintained and deliberately diverge from the repository: the .htaccess
# carries the STAGING ONLY noindex and host-guard blocks that must not ship to
# the live domain (Docs/V2_PRE_CUTOVER_ACTIONS.md §C). That is a reasonable
# arrangement, but it means editing those files in git changes nothing on the
# server, silently, and one of them can break every page on the site.
#
# zlib.output_compression compresses at the SAPI output layer, after
# CompressPublicResponses has already set Content-Encoding: gzip on a body it
# compressed itself. The middleware cannot see zlib and zlib cannot see the
# middleware, so the two together emit gzip(gzip(body)) under a single header:
# every page unreadable, in every browser, triggered by whichever of the two is
# enabled second rather than by any deploy. It was left On here as a
# one-host-change-away optimisation back when nothing compressed; it is now the
# one setting that must never be on.
if [[ -f "${WEB}/.user.ini" ]] && grep -Eq '^[[:space:]]*zlib\.output_compression[[:space:]]*=[[:space:]]*(On|1|true)' "${WEB}/.user.ini"; then
    fail "zlib.output_compression is enabled in ${WEB}/.user.ini. The application compresses in CompressPublicResponses; two compressors produce gzip(gzip(body)) and break every page. Comment it out - this file is not deployed from git, so it must be edited on the server."
fi

# A missing asset directory does not error, it renders the whole site unstyled,
# so check the source before touching anything on the server.
[[ -f "${SOURCE}/public/build/manifest.json" ]] || fail \
    "No Vite manifest in the release at ${SOURCE}/public/build. Run 'php artisan view:clear && npm run build' and commit public/build."

# ── Sync source ──────────────────────────────────────────────────────────────
# Only the trees that are code. .env, storage/ and public/build/ are state and
# are never touched. bootstrap/cache is excluded deliberately: shipping a
# manifest built on another machine loads service providers that are not
# installed here, and every page returns 500 (README 8.1).
# Between the source sync and the migration the code and schema disagree, and
# PHP keeps serving. Fine on staging; on the live domain it is a window of 500s.
MAINTENANCE=0
if [[ -f "${APP}/vendor/autoload.php" ]]; then
    (cd "${APP}" && "${PHP}" artisan down --render="errors::503" --retry=60 >/dev/null 2>&1) && MAINTENANCE=1
fi
restore_service() { [[ "${MAINTENANCE}" == "1" ]] && (cd "${APP}" && "${PHP}" artisan up >/dev/null 2>&1) || true; }
trap restore_service EXIT

log "Syncing application source"
for tree in app bootstrap config database lang resources routes; do
    [[ -d "${SOURCE}/${tree}" ]] || fail "Expected source tree ${tree} is missing"
done

if command -v rsync >/dev/null 2>&1; then
    rsync -a --delete \
        --exclude='bootstrap/cache/' \
        "${SOURCE}/app" "${SOURCE}/bootstrap" "${SOURCE}/config" \
        "${SOURCE}/database" "${SOURCE}/lang" "${SOURCE}/resources" \
        "${SOURCE}/routes" "${APP}/"
else
    # Anchored excludes. An unanchored --exclude=public once matched
    # resources/views/public and silently dropped 118 view files (README 8.2).
    tar -C "${SOURCE}" -cf - \
        --exclude='./bootstrap/cache' \
        app bootstrap config database lang resources routes | tar -C "${APP}" -xf -
fi

install -m 0644 "${SOURCE}/artisan" "${APP}/artisan"
install -m 0644 "${SOURCE}/composer.json" "${APP}/composer.json"
install -m 0644 "${SOURCE}/composer.lock" "${APP}/composer.lock"

# ── Front-end assets ─────────────────────────────────────────────────────────
# Published before anything else, and without --delete. Vite content-hashes
# every filename, so new and old assets coexist happily - and they have to,
# because the public page cache holds rendered HTML for an hour. Deleting the
# previous build would strip the stylesheet out from under every page already
# in that cache. Stale hashes accumulate slowly; seven files per release is a
# price worth paying to never serve an unstyled page.
log "Publishing front-end build"
mkdir -p "${WEB}/build"
if command -v rsync >/dev/null 2>&1; then
    rsync -a "${SOURCE}/public/build/" "${WEB}/build/"
else
    cp -R "${SOURCE}/public/build/." "${WEB}/build/"
fi
[[ -f "${WEB}/build/manifest.json" ]] || fail "The build did not land at ${WEB}/build"

# ── Runtime directories ──────────────────────────────────────────────────────
# Missing cache stores fail at runtime, not at deploy time.
log "Ensuring runtime directories"
mkdir -p "${APP}/storage/framework/cache/data" \
         "${APP}/storage/framework/cache/webhook" \
         "${APP}/storage/framework/cache/rate-limiter" \
         "${APP}/storage/framework/sessions" \
         "${APP}/storage/framework/views" \
         "${APP}/storage/logs" \
         "${APP}/bootstrap/cache"
chmod -R 775 "${APP}/storage" "${APP}/bootstrap/cache"

# public_path() must resolve or @vite emits nothing and the site renders unstyled.
ln -sfn "${WEB}" "${APP}/public"

# ── Dependencies ─────────────────────────────────────────────────────────────
if [[ -f "${COMPOSER}" ]]; then
    log "Installing PHP dependencies"
    (cd "${APP}" && "${PHP}" "${COMPOSER}" install --no-dev --optimize-autoloader --no-interaction --no-progress)
else
    printf '⚠ composer.phar not found at %s — skipping dependency install.\n' "${COMPOSER}" >&2
    printf '  Safe only when composer.lock has not changed in this release.\n' >&2
fi

# Without this the next artisan call dies on a require() of a missing autoloader,
# which reads like a broken release rather than a missing dependency install.
[[ -f "${APP}/vendor/autoload.php" ]] || fail \
    "No vendor/autoload.php in ${APP}. Composer has never run here, or it failed above."

# Regenerate the package manifest here rather than inheriting one.
log "Clearing stale compiled state"
rm -f "${APP}"/bootstrap/cache/{packages,services,config,routes-v7,events}.php
rm -rf "${APP}/bootstrap/cache/filament"

# ── Schema ───────────────────────────────────────────────────────────────────
log "Running migrations"
(cd "${APP}" && "${PHP}" artisan migrate --force --no-interaction)

# ── Deterministic redirect data ──────────────────────────────────────────────
# Redirect rules are config that happens to live in a table, not editorial
# content, so they must ship with the code that reads them. 68e928f added eight
# subsite-root rules; the code deployed and the rows did not, and every one of
# those eight became a 301 landing on a 404 - the exact failure the continuity
# guide forbids, live for a day before an audit caught it.
#
# LegacyEntryPointRedirectSeeder is updateOrInsert throughout, so this is safe on
# every deploy and cannot overwrite a rule an editor has changed by hand.
log "Seeding deterministic redirect rules"
(cd "${APP}" && "${PHP}" artisan db:seed --class=LegacyEntryPointRedirectSeeder --force --no-interaction)

# ── Caches ───────────────────────────────────────────────────────────────────
# There is no OPcache on this host, so config and route caches are the only
# compiled state that survives a request. Build them every deploy.
log "Rebuilding framework caches"
(cd "${APP}" && "${PHP}" artisan optimize)

# ── Clearing derived caches ──────────────────────────────────────────────────
# Warming does not overwrite. CacheWarmCommand goes through remember(), which
# returns whatever is already stored - so warming a cache full of pre-deploy
# HTML is a no-op, and visitors kept seeing the previous release for up to the
# full hour of public_page_ttl after every deploy. Discovered when a fix was
# verifiably deployed, verifiably present in the deployed files, and still
# absent from the served page.
#
# This has to run BEFORE the build artefacts below, not just before the warm.
# The sitemap's freshness marker is an entry in this same store, so clearing
# after sitemap:generate erased the record that the sitemap had just been
# written - and launch:validate then reported a sitemap generated ninety seconds
# earlier as stale, on every deploy.
#
# Only the default store is cleared. The webhook replay store and the rate
# limiter are separate stores (config/cache.php) and are deliberately untouched:
# clearing those would drop replay protection and reset limits on deploy.
log "Clearing derived caches so the artefacts below are what gets warmed"
(cd "${APP}" && "${PHP}" artisan cache:clear) || true

# ── Build artefacts ──────────────────────────────────────────────────────────
# Neither of these is in git and neither is produced by deploying code. Miss them
# and the site comes up with a search box that finds nothing and a sitemap served
# from PHP on a five-worker pool. launch:validate fails on both.
log "Rebuilding the search index"
(cd "${APP}" && "${PHP}" artisan search:index)

log "Regenerating the static sitemap"
(cd "${APP}" && "${PHP}" artisan sitemap:generate)

# ── Static assets ────────────────────────────────────────────────────────────
log "Publishing SVG assets"
/bin/bash "${SOURCE}/deploy/v2-staging/publish-svg-assets.sh" "${WEB}/images"

# Restored from the deployment hamza wrote. A worker started before this release
# keeps executing the previous release's code until it is told to stop.
log "Signalling queue workers to restart"
(cd "${APP}" && "${PHP}" artisan queue:restart) || true

# ── End of the maintenance window ────────────────────────────────────────────
# Everything that can leave code and schema disagreeing is done. The window has
# to close HERE, not at the end, because both remaining stages make requests
# through the HTTP kernel - and a maintenance-mode kernel answers every one of
# them with a 503.
#
# It used to close on the EXIT trap, which meant:
#
#   - cache:warm warmed nothing. 4,558 of its targets came back 503, so every
#     deploy handed the first visitor to every page a cold cache. On a host
#     whose entire problem is the cost of serving a page, that is the most
#     expensive request the site can serve, and we were guaranteeing it.
#   - launch:validate was half blind. Every check that goes through the kernel
#     got a 503: robots.txt correctness and admin preview safety failed on every
#     deploy for that reason and no other, and the checks that call services
#     directly passed - which is exactly what a green-and-red-in-the-same-run
#     gate looks like when the failures are an artefact of the harness.
#
# The trap stays as the safety net for a failure before this point.
log "Ending the maintenance window"
restore_service
MAINTENANCE=0

# Also his. Without it every deploy hands the first visitors a cold cache, which
# on this host is the most expensive request the site ever serves. The clear it
# depends on happens further up, before the build artefacts are written.
#
log "Warming caches"
(cd "${APP}" && "${PHP}" artisan cache:warm --include-sitemap) || true

# ── Verify ───────────────────────────────────────────────────────────────────
# A deploy that reports success while the site is down is worse than one that
# fails, so end by proving the application still boots.
log "Verifying"
(cd "${APP}" && "${PHP}" artisan route:list >/dev/null) || fail "Routes do not boot after deploy"
# On the live domain a failing gate is a failed deploy - the check that catches
# the noindex/robots.txt trap lives in there, and shipping past it de-indexes the
# university. On staging it is advisory so a content warning does not block a
# rehearsal.
if [[ "${SPU_DEPLOY_ENV:-staging}" == "production" ]]; then
    (cd "${APP}" && "${PHP}" artisan launch:validate --environment=production) \
        || fail "launch:validate failed. Not completing a production deploy on a red gate."
else
    (cd "${APP}" && "${PHP}" artisan launch:validate) || {
        printf '\n⚠ launch:validate reported problems. The code is deployed; read the output above.\n' >&2
    }
fi

# The tarball-based deployment extracts into .release/ inside the repository,
# which leaves the working tree dirty - and cPanel refuses to deploy a repository
# with uncommitted changes, so the NEXT deploy is blocked by the last one. Clean
# up after ourselves. Harmless when deploying from a normal clone, where this
# directory never exists.
if [[ -d "${SOURCE}/../.release" && -f "${SOURCE}/../deploy.sh" ]]; then
    rm -rf "${SOURCE}/../.release"
fi

log "Deployed $(cd "${SOURCE}" && git rev-parse --short HEAD 2>/dev/null || echo 'unknown')"

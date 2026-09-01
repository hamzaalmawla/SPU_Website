#!/usr/bin/env bash
#
# Deployment task for cPanel Git Version Control on v2.spu.edu.sy.
#
# cPanel clones the repository on the server and runs the tasks in .cpanel.yml
# from that clone. This is the auditable, shell-free deployment mechanism REM-07
# asks for: the executor is cPanel, the input is a commit, and the output is this
# script's log.
#
# There is no Node on this host, so the Vite build cannot run here. public/build
# is therefore committed and ships with the clone - see .gitignore for why - and
# this script publishes it to the web root. Rebuild it before a release with
# `php artisan view:clear && npm run build` and commit the result.
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

# A missing asset directory does not error, it renders the whole site unstyled,
# so check the source before touching anything on the server.
[[ -f "${SOURCE}/public/build/manifest.json" ]] || fail \
    "No Vite manifest in the release at ${SOURCE}/public/build. Run 'php artisan view:clear && npm run build' and commit public/build."

# ── Sync source ──────────────────────────────────────────────────────────────
# Only the trees that are code. .env, storage/ and public/build/ are state and
# are never touched. bootstrap/cache is excluded deliberately: shipping a
# manifest built on another machine loads service providers that are not
# installed here, and every page returns 500 (README 8.1).
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

# ── Caches ───────────────────────────────────────────────────────────────────
# There is no OPcache on this host, so config and route caches are the only
# compiled state that survives a request. Build them every deploy.
log "Rebuilding framework caches"
(cd "${APP}" && "${PHP}" artisan optimize)

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

# ── Verify ───────────────────────────────────────────────────────────────────
# A deploy that reports success while the site is down is worse than one that
# fails, so end by proving the application still boots.
log "Verifying"
(cd "${APP}" && "${PHP}" artisan route:list >/dev/null) || fail "Routes do not boot after deploy"
(cd "${APP}" && "${PHP}" artisan launch:validate) || {
    printf '\n⚠ launch:validate reported problems. The code is deployed; read the output above.\n' >&2
}

log "Deployed $(cd "${SOURCE}" && git rev-parse --short HEAD 2>/dev/null || echo 'unknown')"

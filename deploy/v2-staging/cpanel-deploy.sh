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

# The other half of the staging overlay, and the one with no other safety net.
#
# The docroot .htaccess adds `X-Robots-Tag: noindex, nofollow, noarchive` to
# every response so v2 stays out of Google while it is a rehearsal. Apache adds
# it, which means no check inside PHP can ever see it: launch:validate makes its
# requests through the HTTP kernel and never touches the web server. So the
# robots.txt half of the overlay is caught by the gate and this half is caught
# by nothing.
#
# Left in place at cutover it tells every search engine to drop the university's
# main website. That is the single most expensive mistake available here, it is
# a manual step in a checklist (Docs/V2_PRE_CUTOVER_ACTIONS.md section C), and
# manual steps in checklists are the ones that get missed.
if [[ "${SPU_DEPLOY_ENV:-staging}" == "production" ]] \
   && [[ -f "${WEB}/.htaccess" ]] \
   && grep -Eqi '^[[:space:]]*Header[[:space:]]+(always[[:space:]]+)?set[[:space:]]+X-Robots-Tag.*noindex' "${WEB}/.htaccess"; then
    fail "${WEB}/.htaccess still sets X-Robots-Tag: noindex, and this is a production deploy. Every page would tell search engines not to index the site. Remove the STAGING ONLY blocks - see Docs/V2_PRE_CUTOVER_ACTIONS.md section C. This file is not deployed from git; edit it on the server."
fi

# A missing asset directory does not error, it renders the whole site unstyled,
# so check the source before touching anything on the server.
[[ -f "${SOURCE}/public/build/manifest.json" ]] || fail \
    "No Vite manifest in the release at ${SOURCE}/public/build. Run 'php artisan view:clear && npm run build' and commit public/build."

env_value() {
    grep -E "^[[:space:]]*$1=" "${APP}/.env" | tail -n 1 | cut -d= -f2- | tr -d '\r"' || true
}

# Production migrations are forward-only. Require an operator-supplied reference
# to a verified, restorable database backup before any release files are synced.
APP_ENV_VALUE="$(env_value APP_ENV)"
BACKUP_REFERENCE="${SPU_DATABASE_BACKUP_REFERENCE:-$(env_value SPU_DATABASE_BACKUP_REFERENCE)}"

# ── Pre-migration database dump ──────────────────────────────────────────────
# The gate below is unchanged and still authoritative: nothing is synced without
# a reference to a restorable backup. What changed is that there is now a way to
# produce one from here.
#
# The gate was written for an operator with a shell, who takes a cPanel backup,
# checks it, and writes its name into .env by hand. That remains the preferred
# path and an operator-supplied reference is never overwritten. But shell is
# disabled on this account - UAPI has no SSH module - and cPanel's per-database
# download endpoint is session-authenticated, so it refuses an API token with a
# 403. With no human at a terminal there was no way to satisfy the gate
# honestly, and the only remaining options were to skip the release or to write
# a reference pointing at nothing. This is the third option.
#
# An automatic dump is better than a human at knowing the backup is recent, is
# of this exact database, and is internally complete. It is worse at the thing
# the gate was really protecting: that somebody looked. So the reference it
# writes names itself - `auto-mysqldump-...` - and must never be mistaken for a
# backup a person verified.
#
# This runs BEFORE the maintenance window and BEFORE any file is synced, so
# every failure path here leaves the running site exactly as it was.
# Re-dumps when the only reference present is one of ours from an earlier
# deploy. The first version of this ran solely on an empty reference, which meant
# it took exactly one backup ever: the value it wrote then satisfied the gate
# forever and every later release migrated against a dump from whenever this
# first ran. "A new backup and reference for every migration release" is the
# whole point, so an auto- reference is treated as spent.
#
# An operator-supplied reference never matches this pattern and is still never
# touched - a human who took a backup and wrote its name here keeps that name.
if [[ "${SPU_DEPLOY_ENV:-staging}" == "production" || "${APP_ENV_VALUE}" == "production" ]] \
   && [[ -z "${BACKUP_REFERENCE}" || "${BACKUP_REFERENCE}" == auto-mysqldump-* ]]; then

    log "Taking a pre-migration database dump"

    command -v mysqldump >/dev/null 2>&1 \
        || fail "mysqldump is not on PATH, so no pre-migration backup can be taken and this release cannot proceed. Take a cPanel database backup by hand and set SPU_DATABASE_BACKUP_REFERENCE in ${APP}/.env."

    DB_NAME="$(env_value DB_DATABASE)"
    DB_USER="$(env_value DB_USERNAME)"
    DB_PASS="$(env_value DB_PASSWORD)"
    DB_HOST="$(env_value DB_HOST)"
    DB_PORT="$(env_value DB_PORT)"
    [[ -n "${DB_NAME}" && -n "${DB_USER}" ]] || fail "DB_DATABASE or DB_USERNAME is missing from ${APP}/.env; refusing to deploy without a backup."

    BACKUP_DIR="${SPU_BACKUP_DIR:-/home/spuedu/backups/spu_v2}"
    mkdir -p "${BACKUP_DIR}"
    chmod 700 "${BACKUP_DIR}"

    DUMP_STAMP="$(date -u +%Y%m%d-%H%M%S)"
    DUMP_FILE="${BACKUP_DIR}/${DB_NAME}-${DUMP_STAMP}.sql.gz"

    # The password never appears on a command line: ps is readable by other
    # accounts, and this script's stdout becomes a log file cPanel keeps.
    DUMP_CNF="$(umask 077 && mktemp "${BACKUP_DIR}/.my.XXXXXX")"
    cleanup_dump_cnf() { rm -f "${DUMP_CNF}"; }
    trap cleanup_dump_cnf EXIT
    {
        printf '[mysqldump]\n'
        printf 'user=%s\n' "${DB_USER}"
        printf 'password=%s\n' "${DB_PASS}"
        [[ -n "${DB_HOST}" ]] && printf 'host=%s\n' "${DB_HOST}"
        [[ -n "${DB_PORT}" ]] && printf 'port=%s\n' "${DB_PORT}"
    } > "${DUMP_CNF}"

    # --single-transaction keeps this non-blocking on InnoDB: the site stays up
    # and consistent while the dump runs. Routines and triggers are part of the
    # schema and a restore without them is not the database that was backed up.
    ( umask 077 && mysqldump --defaults-extra-file="${DUMP_CNF}" \
        --single-transaction --quick --routines --triggers --events \
        --default-character-set=utf8mb4 \
        "${DB_NAME}" | gzip -6 > "${DUMP_FILE}" ) \
        || fail "mysqldump failed. Nothing has been synced and the site is untouched."

    # mysqldump exiting 0 is not a restorable backup. Each of these has a real
    # failure behind it: a dump truncated by a full disk still exits 0 through a
    # pipe, and gzip will happily write a corrupt tail.
    [[ -s "${DUMP_FILE}" ]] || fail "The dump at ${DUMP_FILE} is empty."
    gzip -t "${DUMP_FILE}" 2>/dev/null || fail "The dump at ${DUMP_FILE} is not a valid gzip stream."
    gzip -cd "${DUMP_FILE}" | tail -c 2000 | grep -q 'Dump completed' \
        || fail "The dump at ${DUMP_FILE} has no completion trailer, so it is truncated."
    DUMP_TABLES="$(gzip -cd "${DUMP_FILE}" | grep -c '^CREATE TABLE' || true)"
    [[ "${DUMP_TABLES}" -ge 20 ]] \
        || fail "The dump holds only ${DUMP_TABLES} tables, which is too few to be this database. Refusing to treat it as a backup."

    # Remove the credentials file here rather than leaving it to the trap: the
    # maintenance-window trap installed further down replaces this one, and a
    # file holding the database password must not outlive the command that
    # needed it.
    cleanup_dump_cnf
    trap - EXIT

    chmod 600 "${DUMP_FILE}"
    DUMP_SHA="$(sha256sum "${DUMP_FILE}" | cut -c1-12)"
    DUMP_SIZE="$(du -h "${DUMP_FILE}" | cut -f1)"
    BACKUP_REFERENCE="auto-mysqldump-${DB_NAME}-${DUMP_STAMP}-${DUMP_SHA}"

    printf '  %s\n  %s tables, %s, sha256 %s…\n' "${DUMP_FILE}" "${DUMP_TABLES}" "${DUMP_SIZE}" "${DUMP_SHA}"

    # Written into .env so the reference survives for a human reading it later,
    # and so a re-run of this deploy reuses the dump rather than taking another.
    if grep -qE '^[[:space:]]*SPU_DATABASE_BACKUP_REFERENCE=' "${APP}/.env"; then
        sed -i.bak -E "s|^[[:space:]]*SPU_DATABASE_BACKUP_REFERENCE=.*|SPU_DATABASE_BACKUP_REFERENCE=${BACKUP_REFERENCE}|" "${APP}/.env"
        rm -f "${APP}/.env.bak"
    else
        printf '\nSPU_DATABASE_BACKUP_REFERENCE=%s\n' "${BACKUP_REFERENCE}" >> "${APP}/.env"
    fi

    # The account runs close to its disk quota, and a dump per release adds up.
    # Three is enough to cover "the release before this one" without becoming an
    # archive nobody prunes.
    # `|| true` throughout: set -o pipefail would otherwise turn "there are no
    # old dumps to prune" into a failed deployment.
    (ls -1t "${BACKUP_DIR}/${DB_NAME}-"*.sql.gz 2>/dev/null || true) | tail -n +4 | while read -r old; do
        printf '  pruning old dump %s\n' "$(basename "${old}")"
        rm -f "${old}"
    done || true
fi

if [[ "${SPU_DEPLOY_ENV:-staging}" == "production" || "${APP_ENV_VALUE}" == "production" ]]; then
    [[ -n "${BACKUP_REFERENCE}" ]] || fail \
        "Production deployment requires SPU_DATABASE_BACKUP_REFERENCE naming a verified, restorable pre-deployment database backup."
    log "Verified pre-deployment database backup: ${BACKUP_REFERENCE}"
fi

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

# nginx strips Accept-Encoding before Apache or PHP ever sees it (measured
# 2 September; the reasoning is in config/edge.php). That means mod_deflate
# cannot fire for a static file and no negotiated rewrite can either, so the
# build assets were going out uncompressed: app.css alone is 311 KB on the wire
# where it gzips to 51 KB, on an audience the .htaccess notes is "largely on
# slow connections".
#
# The application already forces gzip on HTML for exactly this reason. These
# siblings extend the same, already accepted trade-off to the build assets.
# public/.htaccess serves a .gz only when it exists, so deleting one here just
# restores the uncompressed file - this step is safe to fail.
log "Pre-compressing build assets"
if command -v gzip >/dev/null 2>&1; then
    compressed=0
    while IFS= read -r asset; do
        # -n keeps the timestamp out of the output so an unchanged asset
        # produces an identical .gz and rsync has nothing to copy next time.
        if gzip -9 -n -c "${asset}" > "${asset}.gz" 2>/dev/null; then
            compressed=$((compressed + 1))
        else
            rm -f "${asset}.gz"
        fi
    done < <(find "${WEB}/build" -type f \( -name '*.css' -o -name '*.js' \) -size +1k)
    printf '  Compressed %d build asset(s)\n' "${compressed}"
else
    printf '  gzip not available; assets will be served uncompressed\n' >&2
fi

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

# ── Approved legacy faculty-project import ───────────────────────────────────
# Runs an editorially approved one-off import that otherwise needs a shell, and
# this account has none: UAPI reports no SSH module, so there is no terminal on
# this host for anyone.
#
# This is deliberately NOT a general way to run commands here. REM-07 forbids an
# execution bridge, and it means a surface that runs whatever it is handed. This
# runs one hardcoded command, against one hardcoded expected result, and refuses
# everything else. Three things keep it on the right side of that line:
#
#   1. It is inert unless a human places an approval file that is not in git and
#      cannot be created by a deploy. No file, no import, silently.
#   2. The dump's sha256 must match the one recorded in that approval file, so
#      the bytes imported are provably the bytes that were approved.
#   3. The dry run must report the approved manifest EXACTLY. Any other number -
#      more, fewer, a faculty off by one - fails the deploy before anything is
#      written.
#
# It disarms itself after a successful write, so a redeploy cannot run it twice.
#
# Placed after migrate because it needs the schema, and before the cache, search
# and sitemap steps below - which are exactly the post-import commands the
# runbook asks to be run afterwards, so they pick the new records up.
IMPORT_DIR="${APP}/storage/app/private/legacy-imports"
IMPORT_APPROVAL="${IMPORT_DIR}/faculty-projects.approved"
IMPORT_SURVEY="${IMPORT_DIR}/faculty-projects.survey"

# ── Faculty-project survey (read-only) ───────────────────────────────────────
# Answers one question and changes nothing: how many importable projects does
# the legacy database hold RIGHT NOW, broken down by faculty.
#
# It exists because the approved manifest was measured against a dump taken on
# 2026-08-27 which nobody can now find. The copies that turned up are from
# 2026-07-28 and hold 473 projects, not 492 - so importing them would silently
# drop 19 and would stamp the 20260827 approval on content it was never granted
# for. Rather than guess, this reads the live legacy database and reports what
# is actually there, so the editorial decision is made against real numbers.
#
# Strictly read-only on both sides: it connects with OLD_DB_USERNAME, the
# SELECT-only legacy user, and runs the importer WITHOUT --write. No row in
# either database is created, changed or deleted.
#
# Like the import, it is inert unless a human places the marker file, and it
# removes that marker afterwards so it runs once rather than on every deploy.
if [[ -f "${IMPORT_SURVEY}" ]]; then
    log "Faculty-project survey (read-only; nothing will be written)"

    command -v mysqldump >/dev/null 2>&1 || fail "mysqldump is not on PATH; cannot survey the legacy database."

    OLD_DB_NAME="$(env_value OLD_DB_DATABASE)"
    OLD_DB_USER="$(env_value OLD_DB_USERNAME)"
    OLD_DB_PASS="$(env_value OLD_DB_PASSWORD)"
    OLD_DB_HOST_V="$(env_value OLD_DB_HOST)"
    [[ -n "${OLD_DB_NAME}" && -n "${OLD_DB_USER}" ]] \
        || fail "OLD_DB_DATABASE or OLD_DB_USERNAME is missing from ${APP}/.env; cannot survey the legacy database."

    SURVEY_CNF="$(umask 077 && mktemp "${IMPORT_DIR}/.mysurvey.XXXXXX")"
    SURVEY_DUMP="${IMPORT_DIR}/legacy-survey-$(date -u +%Y%m%d-%H%M%S).sql"
    # restore_service is called here too: this trap REPLACES the maintenance
    # trap installed further up, so without it a failed survey would leave the
    # site in maintenance mode serving 503s until someone noticed.
    cleanup_survey() { rm -f "${SURVEY_CNF}" "${SURVEY_DUMP}"; restore_service; }
    trap cleanup_survey EXIT
    {
        printf '[mysqldump]\n'
        printf 'user=%s\n' "${OLD_DB_USER}"
        printf 'password=%s\n' "${OLD_DB_PASS}"
        [[ -n "${OLD_DB_HOST_V}" ]] && printf 'host=%s\n' "${OLD_DB_HOST_V}"
    } > "${SURVEY_CNF}"

    # Only the two tables the importer reads. The rest of the legacy database is
    # 225 MB of content this does not need and should not copy.
    ( umask 077 && mysqldump --defaults-extra-file="${SURVEY_CNF}" \
        --single-transaction --quick --no-create-info=false \
        --default-character-set=utf8mb4 \
        "${OLD_DB_NAME}" jx_categories jx_items > "${SURVEY_DUMP}" ) \
        || fail "Could not read the legacy database for the survey. Nothing was changed."

    printf '  dumped jx_categories + jx_items (%s)\n' "$(du -h "${SURVEY_DUMP}" | cut -f1)"

    log "Faculty-project survey: dry run against the CURRENT legacy data"
    (cd "${APP}" && "${PHP}" -d memory_limit=1024M artisan legacy-import:faculty-projects "${SURVEY_DUMP}" --json) \
        || printf '\n  survey dry run reported a problem; see the output above\n' >&2

    rm -f "${SURVEY_CNF}" "${SURVEY_DUMP}"
    trap restore_service EXIT
    mv "${IMPORT_SURVEY}" "${IMPORT_SURVEY}.done-$(date -u +%Y%m%d-%H%M%S)"
    printf '  survey complete; nothing was written\n'
fi

if [[ -f "${IMPORT_APPROVAL}" ]]; then
    log "Approved faculty-project import found"

    IMPORT_TOKEN="$(grep -E '^token=' "${IMPORT_APPROVAL}" | head -n 1 | cut -d= -f2- | tr -d '\r')"
    IMPORT_SOURCE="$(grep -E '^source=' "${IMPORT_APPROVAL}" | head -n 1 | cut -d= -f2- | tr -d '\r')"
    [[ -n "${IMPORT_TOKEN}" ]] || fail "The approval file has no token= line."

    IMPORT_GENERATED=0
    if [[ "${IMPORT_SOURCE}" == "legacy-db" ]]; then
        # Sourced from the live legacy database rather than a file, because the
        # 2026-08-27 dump the original approval names cannot be produced by
        # anyone and the only copies that exist are a month older and nineteen
        # projects short.
        #
        # There is no sha256 to check here - the dump is made seconds before it
        # is read, so a hash would only prove the file did not change in those
        # seconds, which nothing threatens. The real guarantee is the manifest
        # check below: the dry run must report the approved counts EXACTLY, so
        # if the legacy data has moved since the approval was given, by even one
        # project in one faculty, this deploy fails without writing.
        log "Faculty-project import: dumping the live legacy database"
        command -v mysqldump >/dev/null 2>&1 || fail "mysqldump is not on PATH; cannot source the import."

        OLD_DB_NAME="$(env_value OLD_DB_DATABASE)"
        OLD_DB_USER="$(env_value OLD_DB_USERNAME)"
        OLD_DB_PASS="$(env_value OLD_DB_PASSWORD)"
        OLD_DB_HOST_V="$(env_value OLD_DB_HOST)"
        [[ -n "${OLD_DB_NAME}" && -n "${OLD_DB_USER}" ]] \
            || fail "OLD_DB_DATABASE or OLD_DB_USERNAME is missing; cannot source the import."

        IMPORT_CNF="$(umask 077 && mktemp "${IMPORT_DIR}/.myimp.XXXXXX")"
        IMPORT_DUMP="${IMPORT_DIR}/legacy-import-$(date -u +%Y%m%d-%H%M%S).sql"
        IMPORT_GENERATED=1
        # Same reason as the survey: this replaces the maintenance trap, so it
        # has to lift maintenance itself on the way out.
        cleanup_import() { rm -f "${IMPORT_CNF}" "${IMPORT_DUMP}"; restore_service; }
        trap cleanup_import EXIT
        {
            printf '[mysqldump]\n'
            printf 'user=%s\n' "${OLD_DB_USER}"
            printf 'password=%s\n' "${OLD_DB_PASS}"
            [[ -n "${OLD_DB_HOST_V}" ]] && printf 'host=%s\n' "${OLD_DB_HOST_V}"
        } > "${IMPORT_CNF}"

        ( umask 077 && mysqldump --defaults-extra-file="${IMPORT_CNF}" \
            --single-transaction --quick --default-character-set=utf8mb4 \
            "${OLD_DB_NAME}" jx_categories jx_items > "${IMPORT_DUMP}" ) \
            || fail "Could not read the legacy database. Nothing was written."
        rm -f "${IMPORT_CNF}"
        printf '  sourced %s from %s\n' "$(du -h "${IMPORT_DUMP}" | cut -f1)" "${OLD_DB_NAME}"
    else
        IMPORT_DUMP="${IMPORT_DIR}/$(grep -E '^dump=' "${IMPORT_APPROVAL}" | head -n 1 | cut -d= -f2- | tr -d '\r')"
        IMPORT_SHA="$(grep -E '^sha256=' "${IMPORT_APPROVAL}" | head -n 1 | cut -d= -f2- | tr -d '\r')"

        [[ -f "${IMPORT_DUMP}" ]] || fail "The approval file names a dump that is not present: ${IMPORT_DUMP}"
        [[ -n "${IMPORT_SHA}" ]]  || fail "The approval file has no sha256= line, so the dump cannot be proven to be the approved one."

        ACTUAL_SHA="$(sha256sum "${IMPORT_DUMP}" | cut -d' ' -f1)"
        [[ "${ACTUAL_SHA}" == "${IMPORT_SHA}" ]] || fail \
            "The dump at ${IMPORT_DUMP} does not match the approved sha256. Approved ${IMPORT_SHA}, found ${ACTUAL_SHA}. Refusing to import content nobody approved."
    fi

    log "Faculty-project import: dry run"
    # -d memory_limit: the host sets 128M, and deploy #67 died on exactly that
    # ("Allowed memory size of 134217728 bytes exhausted") during the write. The
    # legacy dump is ~191 MB of SQL and parsing it into PHP arrays costs several
    # times that before media verification adds its own structures. Raised only
    # for this command; every other artisan call in this script keeps the host
    # default.
    IMPORT_DRY="$(cd "${APP}" && "${PHP}" -d memory_limit=1024M artisan legacy-import:faculty-projects "${IMPORT_DUMP}" --json)" \
        || fail "The faculty-project dry run failed. Nothing was written."
    printf '%s\n' "${IMPORT_DRY}"

    # Compared against the approved manifest with a parser rather than a grep:
    # prose output changes, and a check that silently stops matching is worse
    # than no check.
    # The expected counts come from the approval file when it carries them, and
    # fall back to the 2026-08-27 manifest otherwise. Either way a human chose
    # them: the file is placed by hand and cannot be written by a deploy. This
    # matters because the 2026-08-27 dump may never be found, and a later
    # editorial decision against a different, known set must be enforceable with
    # the same rigour rather than by loosening the check.
    IMPORT_EXPECT="$(grep -E '^expect_' "${IMPORT_APPROVAL}" | tr -d '\r' | tr '\n' ';')"

    printf '%s' "${IMPORT_DRY}" | IMPORT_EXPECT="${IMPORT_EXPECT}" "${PHP}" -r '
        $expected = ["total" => 492, "medicine" => 91, "dentistry" => 8, "pharmacy" => 123,
                     "artificial-intelligence" => 98, "petroleum" => 60, "business-administration" => 112];
        foreach (array_filter(explode(";", (string) getenv("IMPORT_EXPECT"))) as $pair) {
            if (! str_contains($pair, "=")) { continue; }
            [$k, $v] = explode("=", $pair, 2);
            $k = strtolower(trim(substr(trim($k), strlen("expect_"))));
            if ($k !== "" && is_numeric(trim($v))) { $expected[$k] = (int) trim($v); }
        }
        $raw = stream_get_contents(STDIN);
        $json = json_decode($raw, true);
        if (! is_array($json)) { fwrite(STDERR, "dry-run output was not JSON\n"); exit(1); }
        $flat = [];
        array_walk_recursive($json, function ($v, $k) use (&$flat) { $flat[strtolower((string) $k)] = $v; });
        // The importer calls the headline figure importable_projects. "total" is
        // what a person writing an approval reaches for, so accept both rather
        // than making the approval file mirror an internal field name.
        $flat["total"] = $flat["importable_projects"] ?? $flat["total"] ?? null;
        $flat["hidden"] = $flat["hidden_projects"] ?? $flat["hidden"] ?? null;
        $flat["visible"] = $flat["visible_projects"] ?? $flat["visible"] ?? null;
        foreach ($expected as $key => $want) {
            $got = $flat[$key] ?? null;
            if ((int) $got !== $want) {
                fwrite(STDERR, sprintf("manifest mismatch: %s expected %d, dry run reported %s\n", $key, $want, var_export($got, true)));
                exit(1);
            }
        }
        fwrite(STDOUT, "  dry run matches the approved manifest exactly\n");
    ' || fail "The dry run does not match the approved 2026-08-27 manifest. The dump, the data, or the approval is not what it should be. Nothing was written."

    log "Faculty-project import: write"
    # Output is captured so it can be recorded, but a failure must still SHOW
    # why: deploy #66 failed here and printed nothing at all, because the
    # message explaining it went into this variable and was then discarded.
    IMPORT_WRITE_LOG="${IMPORT_DIR}/.write-output.$$"
    if ! (cd "${APP}" && "${PHP}" -d memory_limit=1024M artisan legacy-import:faculty-projects "${IMPORT_DUMP}" \
            --write --approve="${IMPORT_TOKEN}" --enable-visible --verify-media --json) \
            > "${IMPORT_WRITE_LOG}" 2>&1; then
        printf '\n--- write output ---\n' >&2
        cat "${IMPORT_WRITE_LOG}" >&2
        rm -f "${IMPORT_WRITE_LOG}"
        fail "The faculty-project write failed; its output is above. Nothing was committed by this step. If rows were partially written, restore the pre-deployment dump named by SPU_DATABASE_BACKUP_REFERENCE."
    fi
    IMPORT_WRITE="$(cat "${IMPORT_WRITE_LOG}")"
    rm -f "${IMPORT_WRITE_LOG}"
    printf '%s\n' "${IMPORT_WRITE}"

    # Disarm before anything else can fail: a half-finished deploy must not
    # leave an armed import behind for the next one to run again.
    mv "${IMPORT_APPROVAL}" "${IMPORT_APPROVAL}.consumed-$(date -u +%Y%m%d-%H%M%S)"
    rm -f "${IMPORT_DUMP}"
    [[ "${IMPORT_GENERATED}" == "1" ]] && trap restore_service EXIT
    printf '  approval consumed and dump removed\n'
fi

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

# The migration block above prints only what ran on THIS deploy, which cannot
# answer "is migration X applied" for anything that ran on an earlier one - the
# question a release checklist actually asks. This prints the full applied set
# into the deploy log, where it is part of the release record.
log "Migration status"
(cd "${APP}" && "${PHP}" artisan migrate:status) || true
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

# What is still unpublished.
#
# launch:validate answers "does the application work"; it cannot answer "is
# there anything on the page", and those fail differently. Public pages no
# longer fall back to the development fixtures, so a section with nothing
# published renders its empty state correctly and silently - which is right for
# the visitor and useless for anyone trying to find out what is left before
# launch. Someone had to read the source to learn why the media gallery was
# blank; this prints the same answer for every section, every deploy.
#
# --summary, not the full list. A missing payload is usually not an empty page:
# most sections render from database records and treat the payload as an
# optional override, so 110 of 134 report nothing published while showing real
# content. Printing all 110 every deploy would train people to skip the section
# that is supposed to warn them. The summary says how many, and points at
# --probe, which renders each page and reports the ones that are genuinely
# blank.
#
# Never fatal: what to publish, retire or leave empty is SPU's decision, not a
# deploy's.
log "CMS content status"
(cd "${APP}" && "${PHP}" artisan cms:content-status --summary) || \
    printf '   (cms:content-status unavailable in this release)\n'

# The tarball-based deployment extracts into .release/ inside the repository,
# which leaves the working tree dirty - and cPanel refuses to deploy a repository
# with uncommitted changes, so the NEXT deploy is blocked by the last one. Clean
# up after ourselves. Harmless when deploying from a normal clone, where this
# directory never exists.
if [[ -d "${SOURCE}/../.release" && -f "${SOURCE}/../deploy.sh" ]]; then
    rm -rf "${SOURCE}/../.release"
fi

log "Deployed $(cd "${SOURCE}" && git rev-parse --short HEAD 2>/dev/null || echo 'unknown')"

#!/usr/bin/env bash
#
# The post-deploy steps of deploy/v2-staging/cpanel-deploy.sh, for the Docker
# host. Run once per release, from /srv/spu, before the long-running services
# are recreated on the new image:
#
#     docker compose run --rm release
#
# What is the same as on cPanel: the maintenance window, the data-migration
# gate (storage/app/private/legacy-imports/data-migrations.approved, one
# `migration=<name>` line per pending migration that writes rows), migrations,
# the deterministic redirect rows, image derivatives, the search index, the
# static sitemap, queue restart, cache warming and launch:validate.
#
# What is not here: the pre-migration dump (deploy.sh takes it on the host,
# before this runs), copying source trees (the image is the release), and the
# approval-file data jobs (study plans, people unify, ...). With a shell on the
# server those are run directly when they are wanted:
#     docker compose exec web spu-entrypoint artisan <command> ...

set -euo pipefail

APP=/var/www/spu
cd "${APP}"
as_www() { setpriv --reuid=www-data --regid=www-data --init-groups "$@"; }
artisan() { as_www php -d memory_limit=1024M artisan "$@"; }
log()  { printf '\n▸ %s\n' "$1"; }
fail() { printf '\n✗ %s\n' "$1" >&2; exit 1; }

env_value() { sed -n "s/^[[:space:]]*$1=//p" "${APP}/.env" | tail -1 | tr -d '"'"'"'\r'; }

log "Preconditions"
[[ -n "$(env_value APP_KEY)" ]] || fail "APP_KEY is empty. Copy it from the cPanel .env: encrypted data (2FA secrets, sessions, assistant memory) only opens with the same key."
admin_path="$(env_value ADMIN_PATH)"
[[ ${#admin_path} -ge 12 && "${admin_path}" != "admin" ]] || fail "ADMIN_PATH is missing or guessable. Keep the value from the cPanel .env."
[[ "$(env_value APP_DEBUG)" == "false" ]] || fail "APP_DEBUG must be false."
for i in $(seq 1 30); do
    artisan db:show --no-ansi >/dev/null 2>&1 && break
    [[ $i -eq 30 ]] && fail "The database does not answer. Is the db service healthy?"
    sleep 2
done
printf '   ✓ .env, admin path and database\n'

# Rebuild this container's caches from the image being released.
rm -f bootstrap/cache/{packages,services,config,routes-v7,events}.php
artisan package:discover --no-ansi >/dev/null

log "Checking pending migrations"
preview="$(artisan migrate --pretend --force --no-interaction 2>&1)" || { printf '%s\n' "${preview}" >&2; fail "Could not preview the migrations. Nothing was changed."; }
if printf '%s\n' "${preview}" | grep -Eiq '(^|[[:space:]])(update|delete[[:space:]]+from|insert[[:space:]]+into)[[:space:]]'; then
    approval="${APP}/storage/app/private/legacy-imports/data-migrations.approved"
    pending="$(printf '%s\n' "${preview}" | grep -Eo '^[[:space:]]*[0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6}_[A-Za-z0-9_]+' | sed 's/[[:space:]]//g' | sort -u || true)"
    approved=""
    [[ -f "${approval}" ]] && approved="$(grep -E '^[[:space:]]*migration=' "${approval}" | cut -d= -f2- | sed 's/[[:space:]]//g' | sort -u || true)"
    if [[ "${SPU_ALLOW_DATA_MIGRATIONS:-0}" != "1" && ( -z "${pending}" || "${pending}" != "${approved}" ) ]]; then
        printf '   Pending migrations that write rows:\n%s\n' "$(printf '     %s\n' ${pending})" >&2
        fail "Data migrations need approval in ${approval} (one 'migration=<name>' line each). Production content was not changed."
    fi
    printf '   data migrations approved\n'
    rm -f "${approval}"
else
    printf '   %s\n' "no row-writing migrations pending"
fi

log "Maintenance window"
artisan down --render="errors::503" --retry=60 >/dev/null 2>&1 || true
trap 'artisan up >/dev/null 2>&1 || true' EXIT

log "Running migrations"
artisan migrate --force --no-interaction

log "Seeding deterministic redirect rules"
artisan db:seed --class=LegacyEntryPointRedirectSeeder --force --no-interaction

log "Rebuilding framework caches"
artisan optimize --no-ansi

log "Generating image derivatives"
artisan media:generate-legacy-derivatives --no-ansi || printf '   failed; pages keep serving the originals\n'

log "Clearing derived caches"
artisan cache:clear || true

log "Rebuilding the search index"
artisan search:index

log "Regenerating the static sitemap"
artisan sitemap:generate

log "Signalling queue workers to restart"
artisan queue:restart || true

log "Ending the maintenance window"
artisan up
trap - EXIT

log "Warming caches"
artisan cache:warm --include-sitemap || true

log "Verifying"
artisan route:list >/dev/null || fail "Routes do not boot"
artisan migrate:status --no-ansi | tail -5 || true
if [[ "${SPU_DEPLOY_ENV:-production}" == "production" ]]; then
    artisan launch:validate --environment=production || fail "launch:validate failed. Read the output above before sending traffic here."
else
    artisan launch:validate || printf '\n⚠ launch:validate reported problems\n' >&2
fi
artisan continuity:validate-redirects --no-ansi || fail "Redirect validation failed."

log "Release finished"

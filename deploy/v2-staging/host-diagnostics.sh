#!/usr/bin/env bash
#
# Read-only cPanel account diagnostics for v2.spu.edu.sy.
#
# Closes the evidence gaps that REM-15 and REM-16 record as "pending provider
# evidence" but which are answerable from the account itself. It reads; it never
# writes, deploys, or changes configuration, so running it cannot be mistaken for
# the execution bridge REM-07 prohibits.
#
# The three real blockers - OPcache, nginx gzip, PHP-FPM pool sizing - are WHM
# and root changes. Nothing here can fix them. What this does is produce the
# evidence to put in front of whoever can.
#
# Usage:
#   export CPANEL_USER=spuedu
#   export CPANEL_TOKEN=...          # never commit this, never paste it anywhere
#   ./deploy/v2-staging/host-diagnostics.sh > host-evidence-$(date +%Y%m%d).txt
#
# Review the output before sharing it: it contains paths and cron lines, which
# are operational detail rather than secrets, but read them rather than assuming.

set -uo pipefail

: "${CPANEL_USER:?set CPANEL_USER (the cPanel account name, e.g. spuedu)}"
: "${CPANEL_TOKEN:?set CPANEL_TOKEN (a cPanel API token, not a password)}"

HOST="${CPANEL_HOST:-spu.edu.sy}"
PORT="${CPANEL_PORT:-2083}"
AUTH="Authorization: cpanel ${CPANEL_USER}:${CPANEL_TOKEN}"

uapi() {
    local module="$1" function="$2"
    shift 2
    local url="https://${HOST}:${PORT}/execute/${module}/${function}"
    local query=""
    for pair in "$@"; do query="${query}&${pair}"; done
    [ -n "$query" ] && url="${url}?${query#&}"

    curl -sS --max-time 30 -H "$AUTH" "$url"
}

# Some modules UAPI does not carry on this server are still reachable over the
# older API2 endpoint. Same token, same read-only intent.
api2() {
    local module="$1" function="$2"
    curl -sS --max-time 30 -H "$AUTH" \
        --get "https://${HOST}:${PORT}/json-api/cpanel" \
        --data-urlencode "cpanel_jsonapi_user=${CPANEL_USER}" \
        --data-urlencode "cpanel_jsonapi_apiversion=2" \
        --data-urlencode "cpanel_jsonapi_module=${module}" \
        --data-urlencode "cpanel_jsonapi_func=${function}"
}

section() {
    printf '\n========================================\n%s\n========================================\n' "$1"
}

printf 'v2.spu.edu.sy host diagnostics\nAccount: %s   Host: %s   Generated: %s\n' \
    "$CPANEL_USER" "$HOST" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"

# ── Disk quota ───────────────────────────────────────────────────────────────
# The deploy notes assume roughly 12 GB free against 17 GB of legacy media, which
# is why that media is symlinked rather than copied. The account package is 50 GB.
# Those two statements have never been reconciled against the live figure.
section "DISK QUOTA  (reconciles the 12GB-vs-50GB assumption in deploy/v2-staging/README.md)"
uapi Quota get_quota_info

# ── Cron ─────────────────────────────────────────────────────────────────────
# REM-15 requires the scheduler and queue worker to be installed with flock and
# verified. This shows whether they are actually there.
section "CRON LINES  (REM-15: scheduler and queue worker must exist, with flock)"
# UAPI has no Cron module on this server - the call fails with "Can't locate
# Cpanel/API/Cron.pm". API2 still answers, so ask that instead of reporting a
# module-load error where the cron lines should be.
api2 Cron listcron

# ── PHP ──────────────────────────────────────────────────────────────────────
# The v2 vhost must be on ea-php84 while spu.edu.sy stays on ea-php83.
section "PHP VERSION PER VHOST  (v2 must be ea-php84; spu.edu.sy stays ea-php83)"
uapi LangPHP php_get_vhost_versions

section "PHP VERSIONS INSTALLED ON THE SERVER"
uapi LangPHP php_get_installed_versions

# This used to grep the vhost JSON for "version|domain" and call the result an
# extension list. It never contained an extension name, so it could not have
# shown opcache present or absent. A user-level token cannot read the loaded
# extension set at all - that question goes to the host as a question.
#
# What the same response DOES carry, and what nobody was reading, is the FPM
# pool. Those numbers are the capacity ceiling, so they are pulled out here in
# a form somebody can act on rather than left inside a JSON blob.
section "PHP-FPM POOL PER VHOST  (the capacity ceiling - REM-10)"
uapi LangPHP php_get_vhost_versions | python3 -c '
import json, sys

try:
    payload = json.load(sys.stdin)
except json.JSONDecodeError:
    print("  could not parse the vhost response")
    raise SystemExit

rows = payload.get("data") or []
if not rows:
    print("  no vhost data returned:", payload.get("errors"))
    raise SystemExit

header = ("vhost", "php", "fpm", "children", "max_requests")
print("  %-32s %-10s %-4s %-9s %-12s" % header)
for row in sorted(rows, key=lambda r: str(r.get("vhost"))):
    pool = row.get("php_fpm_pool_parms") or {}
    print("  %-32s %-10s %-4s %-9s %-12s" % (
        row.get("vhost"),
        row.get("version"),
        row.get("php_fpm"),
        pool.get("pm_max_children"),
        pool.get("pm_max_requests"),
    ))

print()
print("  pm_max_children is how many requests the site can serve at once.")
print("  pm_max_requests is how many a worker handles before being destroyed;")
print("  at 20, a PHP worker discards its compiled bytecode constantly, which")
print("  is indistinguishable from having no OPcache at all.")
' 2>/dev/null || printf '  (pool summary needs python3; the raw JSON is in the section above)\n'

# ── Domains ──────────────────────────────────────────────────────────────────
section "SUBDOMAINS AND DOCUMENT ROOTS  (v2 docroot must be public_html/spu_v2/public)"
uapi DomainInfo list_domains

# ── SSH ──────────────────────────────────────────────────────────────────────
# REM-07 needs an auditable deployment mechanism. Jailed shell is the approved
# outcome; this shows whether shell access has been enabled on the account yet.
section "SSH KEYS  (REM-07: jailed shell is the approved deployment mechanism)"
uapi SSH list_keys

# ── Database ─────────────────────────────────────────────────────────────────
section "DATABASES  (spuedu_v2 is the app; the legacy user on spuedu_db must be SELECT-only)"
uapi Mysql list_databases
uapi Mysql list_users

# ── Backups ──────────────────────────────────────────────────────────────────
section "BACKUPS  (rollback readiness - is anything automated, or is it all manual?)"
uapi Backup list_backups

printf '\n========================================\nDONE\n========================================\n'
printf 'What this cannot answer, because it needs WHM or root:\n'
printf '  - whether opcache.so is installed and enabled in the FPM runtime (REM-08)\n'
printf '\n'
printf 'No longer open:\n'
printf '  - pm.max_children / pm.max_requests (REM-10) are printed above. The\n'
printf '    account can READ them; it cannot change them - php_set_vhost_versions\n'
printf '    accepts new pool values, returns success, and leaves them untouched.\n'
printf '    Treat any pool change as a WHM request, not an account task.\n'
printf '  - nginx gzip (REM-09) no longer blocks anything. cpanel-deploy.sh writes\n'
printf '    a .gz beside every build asset and public/.htaccess serves it, so CSS\n'
printf '    and JS go out compressed without the edge being fixed. HTML is handled\n'
printf '    separately by CompressPublicResponses.\n'
printf '\n'
printf 'The one request to make of the host, with the pool table above attached:\n'
printf '  raise pm_max_requests from 20 to 500 and pm_max_children above 5, for\n'
printf '  v2.spu.edu.sy and spu.edu.sy, and confirm opcache.so is loaded.\n'
printf 'Send it with the server-load figures: 96 CPUs at ~3%% utilisation and 40%%\n'
printf 'memory means lifting a 5-worker cap costs them nothing. Note that both\n'
printf 'sites carry the same cap, so this is not a staging-only concern - the\n'
printf 'live site has been running on five workers all along.\n'

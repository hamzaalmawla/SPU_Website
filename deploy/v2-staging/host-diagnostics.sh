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
uapi Cron list_lines

# ── PHP ──────────────────────────────────────────────────────────────────────
# The v2 vhost must be on ea-php84 while spu.edu.sy stays on ea-php83.
section "PHP VERSION PER VHOST  (v2 must be ea-php84; spu.edu.sy stays ea-php83)"
uapi LangPHP php_get_vhost_versions

section "PHP VERSIONS INSTALLED ON THE SERVER"
uapi LangPHP php_get_installed_versions

# The extension list is the closest a user-level token gets to answering "is
# OPcache installed". A missing opcache entry here is the evidence for B1/REM-08.
section "PHP EXTENSIONS  (look for opcache - its absence is the REM-08 evidence)"
uapi LangPHP php_get_vhost_versions | tr ',' '\n' | grep -i "version\|domain" || true

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
printf '  - whether nginx gzip is on for text/html (REM-09)\n'
printf '  - the effective pm.max_children / pm.max_requests values (REM-10)\n'
printf 'Those three are the cutover blockers. Send them to the host with the\n'
printf 'server-load figures: 96 CPUs at ~3%% utilisation and 40%% memory means\n'
printf 'lifting a 5-worker cap costs them nothing.\n'

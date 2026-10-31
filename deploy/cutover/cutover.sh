#!/usr/bin/env bash
#
# Promote v2 from the staging host to the live SPU domain.
#
# Everything here is a step that is currently a line in a checklist, and every
# one of them is a step whose omission is silent. Nothing 500s if the canonical
# host is left pointing at v2 - the site simply tells Google that the
# university's address is a staging subdomain. Nothing 500s if the noindex
# header survives - the site simply disappears from search results over the
# following weeks. Manual steps that fail quietly are the ones that get missed,
# so they live in a script that refuses to continue instead.
#
# What this does NOT do:
#
#   * DNS. That stays a human decision, made after this script has reported
#     success and the verification below has passed against the real host.
#   * Secrets. MAIL_* and the Sentry DSN must already be set by a person. This
#     script checks that they are and stops if they are not; it never writes
#     a credential.
#
# Run it on the server, from the deployed application directory:
#
#     bash deploy/cutover/cutover.sh --dry-run     # show every change, touch nothing
#     bash deploy/cutover/cutover.sh               # do it
#     bash deploy/cutover/cutover.sh --rollback    # put staging back
#
# Every write is backed up first, and --rollback restores from that backup.
# Safe to re-run: each step checks whether it has already been applied.

set -euo pipefail

APP="${SPU_APP_PATH:-/home/spuedu/spu_v2_app}"
WEB="${SPU_WEB_PATH:-/home/spuedu/public_html/spu_v2/public}"
PHP="${SPU_PHP_BIN:-/opt/cpanel/ea-php84/root/usr/bin/php}"
SOURCE="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

CANONICAL_HOST="${SPU_CANONICAL_HOST:-spu.edu.sy}"
CANONICAL_URL="https://${CANONICAL_HOST}"
STAGING_HOST="v2.spu.edu.sy"

BACKUP_DIR="${APP}/storage/cutover-backups"
STAMP="$(date -u +%Y%m%d-%H%M%S)"

DRY_RUN=0
ROLLBACK=0

for arg in "$@"; do
    case "${arg}" in
        --dry-run)  DRY_RUN=1 ;;
        --rollback) ROLLBACK=1 ;;
        -h|--help)  sed -n '2,28p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *)          printf 'Unknown option: %s\n' "${arg}" >&2; exit 2 ;;
    esac
done

log()   { printf '\n▸ %s\n' "$1"; }
info()  { printf '   %s\n' "$1"; }
ok()    { printf '   ✓ %s\n' "$1"; }
warn()  { printf '   ⚠ %s\n' "$1" >&2; }
fail()  { printf '\n✗ %s\n' "$1" >&2; exit 1; }
would() { printf '   [dry-run] %s\n' "$1"; }

# ─────────────────────────────────────────────────────────────────────────────
# Rollback
#
# Deliberately first in the file: if this script has gone wrong at 2am, the
# thing being looked for is how to undo it, not how it works.
# ─────────────────────────────────────────────────────────────────────────────
if [[ "${ROLLBACK}" == "1" ]]; then
    log "Rolling back to the most recent cutover backup"
    [[ -d "${BACKUP_DIR}" ]] || fail "No backup directory at ${BACKUP_DIR}. Nothing to roll back to."

    latest="$(ls -1d "${BACKUP_DIR}"/*/ 2>/dev/null | sort | tail -1 || true)"
    [[ -n "${latest}" ]] || fail "No backups found in ${BACKUP_DIR}."
    info "Using ${latest}"

    [[ -f "${latest}/.env" ]]       && cp "${latest}/.env" "${APP}/.env"       && ok "restored .env"
    [[ -f "${latest}/.htaccess" ]]  && cp "${latest}/.htaccess" "${WEB}/.htaccess" && ok "restored .htaccess"
    [[ -f "${latest}/robots.txt" ]] && cp "${latest}/robots.txt" "${WEB}/robots.txt" && ok "restored robots.txt overlay"

    (cd "${APP}" && "${PHP}" artisan config:clear >/dev/null && "${PHP}" artisan config:cache >/dev/null)
    ok "configuration cache rebuilt"

    log "Rolled back. DNS is not touched by this script - if it was already moved, move it back."
    exit 0
fi

# ─────────────────────────────────────────────────────────────────────────────
# Preflight
#
# These are the audit's blockers, expressed as code. The point is that cutover
# becomes impossible while any of them is unresolved, rather than merely
# inadvisable.
# ─────────────────────────────────────────────────────────────────────────────
log "Preflight"

[[ -x "${PHP}" ]]      || fail "PHP binary not found at ${PHP}"
[[ -d "${APP}" ]]      || fail "Application directory ${APP} does not exist"
[[ -f "${APP}/.env" ]] || fail "${APP}/.env is missing"
[[ -d "${WEB}" ]]      || fail "Web root ${WEB} does not exist"

envval() { sed -n "s/^[[:space:]]*$1=//p" "${APP}/.env" | head -1 | tr -d '"'"'"'\r'; }

# Mail. The failure this guards against is invisible from the outside: with
# MAIL_MAILER=log the application renders every message, writes it to a log
# file, reports success, and shows the sender a confirmation page. Contact
# forms, complaints, job applications and two-factor recovery all go nowhere.
mailer="$(envval MAIL_MAILER)"
if [[ "${mailer}" == "log" || "${mailer}" == "array" || -z "${mailer}" ]]; then
    fail "MAIL_MAILER is '${mailer:-unset}'. Every message the site sends would be written to a log file instead of delivered, while the sender is shown a success page. Set a real transport (smtp) and send a test message through the public contact form before running this again."
fi
ok "MAIL_MAILER=${mailer}"

# A queue driver that nothing consumes discards jobs silently. sync is
# acceptable only because it has no consumer to miss; database is acceptable
# only when the worker cron is actually installed.
queue="$(envval QUEUE_CONNECTION)"
if [[ "${queue}" == "database" ]] && ! crontab -l 2>/dev/null | grep -q "queue:work"; then
    fail "QUEUE_CONNECTION=database but no queue:work cron is installed. Queued mail would sit in the jobs table forever with nothing to send it."
fi
ok "QUEUE_CONNECTION=${queue}"

# Error reporting. Not cosmetic: it is the mechanism that surfaces whatever
# this script fails to anticipate, in the hours when it matters most.
if [[ -z "$(envval SENTRY_LARAVEL_DSN)" ]]; then
    fail "SENTRY_LARAVEL_DSN is empty. sentry/sentry-laravel is installed but reporting nowhere, so an exception on the live site would alert no one. Set the DSN and confirm a test exception arrives."
fi
ok "Sentry DSN is set"

if [[ "$(envval APP_DEBUG)" != "false" ]]; then
    fail "APP_DEBUG is not false. Stack traces would be public."
fi
ok "APP_DEBUG=false"

# The one precondition this script cannot verify for itself. A backup that has
# never been restored is a hope, not a rollback plan.
if [[ ! -f "${BACKUP_DIR}/.restore-rehearsed" ]]; then
    warn "No record of a rehearsed database restore (${BACKUP_DIR}/.restore-rehearsed)."
    warn "Restore spuedu_v2 to a scratch database, confirm it opens, then:"
    warn "  mkdir -p ${BACKUP_DIR} && date -u > ${BACKUP_DIR}/.restore-rehearsed"
    if [[ "${DRY_RUN}" == "0" ]]; then
        read -r -p "   Continue without a rehearsed restore? [y/N] " reply
        [[ "${reply}" == "y" || "${reply}" == "Y" ]] || fail "Stopped. Rehearse the restore first."
    fi
else
    ok "Database restore rehearsed $(cat "${BACKUP_DIR}/.restore-rehearsed")"
fi

# ─────────────────────────────────────────────────────────────────────────────
# Backup
# ─────────────────────────────────────────────────────────────────────────────
DEST="${BACKUP_DIR}/${STAMP}"
log "Backing up to ${DEST}"
if [[ "${DRY_RUN}" == "1" ]]; then
    would "create ${DEST} and copy .env, .htaccess, robots.txt into it"
else
    mkdir -p "${DEST}"
    cp "${APP}/.env" "${DEST}/.env"
    chmod 600 "${DEST}/.env"
    [[ -f "${WEB}/.htaccess" ]]  && cp "${WEB}/.htaccess" "${DEST}/.htaccess"
    [[ -f "${WEB}/robots.txt" ]] && cp "${WEB}/robots.txt" "${DEST}/robots.txt"
    ok "backed up"
fi

# ─────────────────────────────────────────────────────────────────────────────
# 1. Canonical host
#
# SESSION_DOMAIN is the one that breaks immediately rather than gradually: left
# at the staging host, no session cookie is valid on the live domain and nobody
# can log in to the admin panel. The other three decay quietly - every canonical
# tag, hreflang alternate and sitemap URL keeps naming the staging subdomain.
# ─────────────────────────────────────────────────────────────────────────────
log "Pointing the application at ${CANONICAL_HOST}"

set_env() {
    local key="$1" value="$2"
    local current; current="$(envval "${key}")"
    if [[ "${current}" == "${value}" ]]; then
        ok "${key} already ${value}"
        return
    fi
    if [[ "${DRY_RUN}" == "1" ]]; then
        would "${key}: ${current:-unset} -> ${value}"
        return
    fi
    if grep -Eq "^[[:space:]]*${key}=" "${APP}/.env"; then
        # A literal replacement, so a value containing / or & cannot corrupt
        # the file the way an unescaped sed replacement would.
        python3 - "${APP}/.env" "${key}" "${value}" <<'PY'
import re, sys
path, key, value = sys.argv[1], sys.argv[2], sys.argv[3]
with open(path) as fh:
    lines = fh.readlines()
out = []
for line in lines:
    if re.match(rf'^\s*{re.escape(key)}=', line):
        out.append(f'{key}={value}\n')
    else:
        out.append(line)
with open(path, 'w') as fh:
    fh.writelines(out)
PY
    else
        printf '%s=%s\n' "${key}" "${value}" >> "${APP}/.env"
    fi
    ok "${key} -> ${value}"
}

set_env APP_URL "${CANONICAL_URL}"
set_env APP_CANONICAL_URL "${CANONICAL_URL}"
set_env SESSION_DOMAIN "${CANONICAL_HOST}"
set_env ENFORCE_CANONICAL_HOST true

# ─────────────────────────────────────────────────────────────────────────────
# 2. Staging overlays
#
# The docroot .htaccess is hand-maintained and deliberately diverges from the
# repository, so nothing in git can remove these for you. Three separate blocks
# have to go, and the host guard is the one that takes the site down rather
# than merely hiding it: it 404s every request whose Host is not the staging
# subdomain, which after cutover is all of them.
# ─────────────────────────────────────────────────────────────────────────────
log "Removing the staging overlays"

if [[ ! -f "${WEB}/.htaccess" ]]; then
    fail "${WEB}/.htaccess does not exist. Refusing to continue - the docroot is not what this script expects."
fi

strip_staging_blocks() {
    python3 - "$1" "${CANONICAL_HOST}" "${STAGING_HOST}" <<'PY'
import re, sys
path, canonical, staging = sys.argv[1], sys.argv[2], sys.argv[3]
src = open(path).read()
out, removed = src, []

# The noindex header. Matched on the directive rather than on a comment, so a
# reworded comment cannot cause it to be silently left behind.
pat = re.compile(r'^[ \t]*(?:#[^\n]*\n[ \t]*)*Header\s+(?:always\s+)?set\s+X-Robots-Tag[^\n]*noindex[^\n]*\n',
                 re.IGNORECASE | re.MULTILINE)
if pat.search(out):
    out = pat.sub('', out); removed.append('X-Robots-Tag noindex header')

# The host guard: RewriteCond on HTTP_HOST followed by an unconditional 404.
pat = re.compile(r'^[ \t]*(?:#[^\n]*\n[ \t]*)*RewriteCond\s+%\{HTTP_HOST\}[^\n]*\n[ \t]*RewriteRule\s+\^\s+-\s+\[R=404[^\n]*\n',
                 re.IGNORECASE | re.MULTILINE)
if pat.search(out):
    out = pat.sub('', out); removed.append('staging host guard (404 for non-v2 hosts)')

# The forced-HTTPS origin, which points at the staging host and must point at
# the canonical one. Rewritten rather than removed: without it the site has no
# HTTPS redirect at all.
if staging in out:
    out = out.replace(f'https://{staging}%{{REQUEST_URI}}', f'https://{canonical}%{{REQUEST_URI}}')
    removed.append(f'forced-HTTPS origin retargeted to {canonical}')

# Trailing "STAGING ONLY" markers left on otherwise-good lines.
out = re.sub(r'[ \t]*#[ \t]*STAGING ONLY[^\n]*', '', out)

# Removing the only directive inside a block leaves an empty <IfModule> behind.
# Apache is happy with it; a person reading the file later is not, and an empty
# guard block invites someone to "restore" something into it.
empty_block = re.compile(r'^[ \t]*<IfModule[^>]*>\s*\n(?:[ \t]*\n)*[ \t]*</IfModule>[ \t]*\n', re.MULTILINE)
while empty_block.search(out):
    out = empty_block.sub('', out)
    if 'empty <IfModule> block(s)' not in removed:
        removed.append('empty <IfModule> block(s)')

# The banner still announces a staging overlay, which is exactly the file this
# no longer is. Leaving it there is how a future reader concludes the overlay
# is still in force and goes looking for blocks that are gone.
out = re.sub(
    r'^# ─+\n# v2\.spu\.edu\.sy[^\n]*\n(?:#[^\n]*\n)*?# ─+\n',
    f'# ─────────────────────────────────────────────────────────────────────────────\n'
    f'# {canonical} — LIVE docroot .htaccess\n'
    f'# Promoted from the staging overlay by deploy/cutover/cutover.sh. The\n'
    f'# STAGING ONLY noindex, host-guard and origin blocks have been removed.\n'
    f'# Still hand-maintained: it is not deployed from git.\n'
    f'# ─────────────────────────────────────────────────────────────────────────────\n',
    out, count=1, flags=re.MULTILINE)

# Collapse the runs of blank lines the removals leave behind.
out = re.sub(r'\n{3,}', '\n\n', out)

open(path, 'w').write(out)
print('\n'.join(f'   ✓ removed {r}' for r in removed) or '   ✓ no staging blocks found (already clean)')

# Belt and braces on the one mistake that is worth catching twice. Comments are
# excluded deliberately: the banner this script writes says the word "noindex"
# while explaining that the directive is gone, and a check that cannot tell the
# two apart is a check people learn to ignore.
leftovers = [
    line.strip()
    for line in out.splitlines()
    if 'noindex' in line.lower() and not line.lstrip().startswith('#')
]
if leftovers:
    print('   ⚠ a live directive still mentions noindex - check by hand:', file=sys.stderr)
    for line in leftovers:
        print(f'       {line}', file=sys.stderr)
PY
}

if [[ "${DRY_RUN}" == "1" ]]; then
    tmp="$(mktemp)"; cp "${WEB}/.htaccess" "${tmp}"
    strip_staging_blocks "${tmp}"
    would "the .htaccess changes above would be applied"
    diff -u "${WEB}/.htaccess" "${tmp}" | sed 's/^/   /' || true
    rm -f "${tmp}"
else
    strip_staging_blocks "${WEB}/.htaccess"
fi

# robots.txt. The overlay exists purely to keep the staging host out of search
# results; the application serves a correct one for the live domain once the
# static file stops shadowing it.
if [[ -f "${WEB}/robots.txt" ]]; then
    if [[ "${DRY_RUN}" == "1" ]]; then
        would "remove ${WEB}/robots.txt so the application serves its own"
    else
        rm -f "${WEB}/robots.txt"
        ok "removed the robots.txt overlay"
    fi
else
    ok "no robots.txt overlay present"
fi

# ─────────────────────────────────────────────────────────────────────────────
# 3. Deploy in production mode
#
# cpanel-deploy.sh carries the gates: it refuses to finish if the noindex
# header survived step 2, and it runs launch:validate --environment=production
# as a hard failure rather than a warning. Running it here means the cutover
# cannot complete on a red gate.
# ─────────────────────────────────────────────────────────────────────────────
log "Deploying in production mode"
if [[ "${DRY_RUN}" == "1" ]]; then
    would "SPU_DEPLOY_ENV=production bash ${SOURCE}/deploy/v2-staging/cpanel-deploy.sh"
else
    if ! SPU_DEPLOY_ENV=production bash "${SOURCE}/deploy/v2-staging/cpanel-deploy.sh"; then
        warn "The production deploy failed. Rolling back."
        bash "${BASH_SOURCE[0]}" --rollback
        fail "Cutover aborted and rolled back. Read the deploy output above; nothing was left half-applied."
    fi
fi

# ─────────────────────────────────────────────────────────────────────────────
# 4. Verify
#
# Against the canonical host, not localhost: the failures this is looking for
# live in the web server and the DNS answer, and a loopback request sees
# neither. Until DNS moves these will not resolve, which is why a failure here
# is reported rather than fatal.
# ─────────────────────────────────────────────────────────────────────────────
log "Verifying ${CANONICAL_URL}"

if [[ "${DRY_RUN}" == "1" ]]; then
    would "check robots.txt, X-Robots-Tag, canonical host and admin reachability"
else
    problems=0
    probe() { curl -sS --max-time 20 "$@" 2>/dev/null || true; }

    if probe "${CANONICAL_URL}/robots.txt" | grep -q 'Disallow: /$'; then
        warn "robots.txt still disallows crawling"; problems=$((problems + 1))
    else
        ok "robots.txt allows crawling"
    fi

    if probe -o /dev/null -D - "${CANONICAL_URL}/ar" | grep -qi '^x-robots-tag.*noindex'; then
        warn "X-Robots-Tag noindex is still being sent"; problems=$((problems + 1))
    else
        ok "no noindex header"
    fi

    if probe "${CANONICAL_URL}/ar" | grep -q "${STAGING_HOST}"; then
        warn "the staging host still appears in the rendered homepage (canonical or hreflang)"; problems=$((problems + 1))
    else
        ok "no staging host in the homepage markup"
    fi

    code="$(probe -o /dev/null -w '%{http_code}' "${CANONICAL_URL}/admin")"
    [[ "${code}" == "302" || "${code}" == "200" ]] \
        && ok "admin reachable (${code})" \
        || { warn "admin returned ${code}"; problems=$((problems + 1)); }

    if [[ "${problems}" -gt 0 ]]; then
        warn "${problems} check(s) failed. If DNS has not moved yet this is expected - re-run this script's verification afterwards. If it has, roll back: bash ${BASH_SOURCE[0]} --rollback"
    else
        ok "all external checks passed"
    fi
fi

# ─────────────────────────────────────────────────────────────────────────────
log "Cutover steps complete"
cat <<EOF

   Still to do, by a person:

     1. Move DNS to this host.
     2. Re-run the verification above once it has propagated.
     3. Submit the sitemap in Search Console: ${CANONICAL_URL}/sitemap.xml
     4. Send a real message through the public contact form and confirm it
        arrives in a human inbox.
     5. Watch Sentry, mail deliverability and Search Console coverage daily
        for the first week.
     6. Confirm certificate renewal before 2026-10-24 - every certificate on
        this account expires that day.

   To undo everything this script changed:

     bash ${BASH_SOURCE[0]} --rollback

EOF

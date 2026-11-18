#!/usr/bin/env bash
#
# SPU website: move v2 from the old cPanel host to this VPS, step by step.
#
#     sudo bash migrate.sh             walk through every step; press Enter to go on.
#                                      Run it again any time: it continues where it stopped.
#     sudo bash migrate.sh final       at switch time: fresh copy of files and databases,
#                                      restart, checks (run after editors stop editing)
#     sudo bash migrate.sh after-dns   after the DNS records point here: HTTPS, live checks,
#                                      nightly backups
#     sudo bash migrate.sh status      which steps are done
#     sudo bash migrate.sh step <N>    run one step again
#     sudo bash migrate.sh report      print the result and the DNS / rollback steps again
#
# How the old server is read (chosen in step 2):
#   - api: a cPanel API token over port 2083 only. For when FTP and the cPanel
#     login page are closed to this server. The old server packs what is needed
#     itself (a Git deploy that only exports, deploy/v2-staging/vps-export.sh) and
#     this server reads the pieces back through the API (scripts/cpanel-api.py).
#     Only the important data: v2's .env, storage and runtime files, the
#     databases, and on request /forms, /Medicine, /spuh and the old media.
#   - ftp: FTP over TLS, or the cPanel password over HTTPS.
#
# Safe by design:
#   - The old server is only read. With the API token the only things written
#     there are the request file ~/vps-export.request and the packed copies in
#     ~/vps-export/ (deleted again after each download). The site, its files
#     and its databases are not changed.
#   - DNS is never changed by this script. It prints the exact records to change.
#   - Every step can be run again. Copies only fetch new or changed files.
#   - Passwords are asked for, never written in this file. The FTP password is
#     kept in /srv/spu/secrets/ftp.env (readable by root only) for re-runs; the
#     cPanel password is kept in memory only.
#
# Full background: Docs/VPS_MIGRATION_RUNBOOK.md and deploy/docker/README.md.

set -uo pipefail
umask 022

VERSION="2026-11-18"
ROOT="${SPU_ROOT:-/srv/spu}"
SRC="${ROOT}/src"
DOCK="${SRC}/deploy/docker"
SPU="${DOCK}/bin/spu"
DATA="${ROOT}/data"
SECRETS="${ROOT}/secrets"
CONF="${SECRETS}/migrate.conf"
STATE="${ROOT}/migrate.state"
CHECKS="${ROOT}/migrate-checks.txt"
REPORT="${ROOT}/MIGRATION-REPORT.txt"
LOGDIR="${ROOT}/migrate-logs"
SELF="$(readlink -f "${BASH_SOURCE[0]}")"

REPO_URL_DEFAULT="https://github.com/hamzaalmawla/SPU_Website.git"
MIGRATION_BRANCHES=(claude/project-thread-01k9u9 claude/project-thread-w8no6t)
OLD_IP_DEFAULT="184.107.185.129"
API_ENV="${SECRETS}/cpanel-api.env"
SITE_DB="spuedu_v2"
# database : folder of the app that uses it
APP_DBS=("spuedu_forms:forms" "spuedu_medical:Medicine" "spuedu_spuh:spuh")

STEP_IDS=(prereqs settings code prepare connect site apps media appenv databases tls start checks report)
STEP_TITLES=(
    "Check this server and install what is missing"
    "Answer a few questions (domain, old server, passwords)"
    "Download the website code"
    "Prepare folders, firewall and stack settings"
    "Test the connection to the old server (read-only)"
    "Copy the website's own files (.env and uploads)"
    "Copy the /forms, /Medicine and /spuh applications"
    "Copy the old site's images and downloads (about 20 GB)"
    "Build the website settings (.env) and check e-mail"
    "Bring the databases from the old server and load them"
    "HTTPS certificate"
    "Build and start the website"
    "Check everything: pages, old links, redirects"
    "Result, DNS switch steps and rollback steps"
)

# ── screen helpers ─────────────────────────────────────────────────────────────
if [[ -t 1 ]]; then
    B=$'\e[1m'; G=$'\e[32m'; R=$'\e[31m'; Y=$'\e[33m'; C=$'\e[36m'; N=$'\e[0m'
else
    B=""; G=""; R=""; Y=""; C=""; N=""
fi
say()  { printf '%s\n' "$*"; }
info() { printf '   %s\n' "$*"; }
ok()   { printf '   %s[ OK ]%s %s\n' "${G}" "${N}" "$*"; }
warn() { printf '   %s[WARN]%s %s\n' "${Y}" "${N}" "$*"; }
bad()  { printf '   %s[FAIL]%s %s\n' "${R}" "${N}" "$*"; }
line() { printf '%s\n' "────────────────────────────────────────────────────────────────────────"; }

# Answers are read from the terminal (or from MIGRATE_INPUT, used by the tests).
exec 3<"${MIGRATE_INPUT:-/dev/tty}" || { echo "Run this script in a terminal." >&2; exit 1; }
readin() { IFS= read -r "$@" <&3; }

pause() {
    local a=""
    printf '\n'
    readin -p "   ${B}Press Enter to continue${N} (or type q to stop here): " a || a="q"
    if [[ "${a}" == q* || "${a}" == Q* ]]; then
        say ""; say "   Stopped. Run the same command again to continue from here."
        exit 0
    fi
}

yesno() {  # yesno "Question" Y|N   -> returns 0 for yes
    local def="${2:-Y}" a hint
    [[ "${def}" == Y ]] && hint="Y/n" || hint="y/N"
    while :; do
        readin -p "   $1 [${hint}]: " a || a=""
        a="${a:-${def}}"
        # y/n, and the same keys on an Arabic keyboard (غ / ى), and نعم / لا
        case "${a,,}" in
            y|yes|غ|نعم|ن) return 0 ;;
            n|no|ى|لا|ل) return 1 ;;
        esac
        info "Please type y (yes) or n (no)."
    done
}

conf_set() {  # conf_set KEY value   (non-secret answers, kept for re-runs)
    local key="$1" val="$2" tmp
    mkdir -p "${SECRETS}"; chmod 700 "${SECRETS}"
    touch "${CONF}"; chmod 600 "${CONF}"
    tmp="$(mktemp "${CONF}.XXXX")"
    grep -v "^${key}=" "${CONF}" > "${tmp}" || true
    printf '%s=%q\n' "${key}" "${val}" >> "${tmp}"
    mv "${tmp}" "${CONF}"; chmod 600 "${CONF}"
}
# shellcheck disable=SC1090
conf_load() { [[ -f "${CONF}" ]] && source "${CONF}"; return 0; }

ask() {  # ask VAR "Question" [default]  -> saved for re-runs
    local var="$1" q="$2" def="${3-}" a
    [[ -n "${!var-}" ]] && def="${!var}"
    while :; do
        readin -p "   ${q}${def:+ [${def}]}: " a || a=""
        a="${a:-${def}}"
        [[ -n "${a}" ]] && break
        info "This cannot be empty."
    done
    printf -v "${var}" '%s' "${a}"
    conf_set "${var}" "${a}"
}

ask_secret() {  # ask_secret VAR "Question"   (not shown, not saved by this function)
    local var="$1" q="$2" a
    while :; do
        readin -s -p "   ${q} (typing is hidden): " a || a=""
        printf '\n'
        [[ -n "${a}" ]] && break
        info "This cannot be empty."
    done
    printf -v "${var}" '%s' "${a}"
}

# KEY=VALUE files (stack.env, app.env): replace or add one key.
env_set() {
    python3 - "$1" "$2" "$3" <<'PY'
import re, sys
path, key, val = sys.argv[1:4]
try:
    lines = open(path, encoding="utf-8").read().splitlines(True)
except FileNotFoundError:
    lines = []
out, found = [], False
for l in lines:
    if re.match(r"^\s*" + re.escape(key) + r"=", l):
        if not found:
            out.append(f"{key}={val}\n")
            found = True
        continue
    out.append(l)
if not found:
    if out and not out[-1].endswith("\n"):
        out[-1] += "\n"
    out.append(f"{key}={val}\n")
open(path, "w", encoding="utf-8").writelines(out)
PY
}
stack_set() {  # stack.env is read by docker compose AND sourced by bash: quote values with spaces or ';'
    local v="$2"
    [[ "${v}" =~ [[:space:]\;] ]] && v="\"${v}\""
    env_set "${ROOT}/stack.env" "$1" "${v}"
}
env_get() {  # env_get file KEY   (value without surrounding quotes)
    [[ -f "$1" ]] || return 0
    sed -n "s/^[[:space:]]*$2=//p" "$1" | tail -1 | sed -e 's/[[:space:]]*$//' -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"
}
dotenv_quote() {  # a value as Laravel's .env reads it literally
    local v="$1"
    if [[ "${v}" =~ ^[A-Za-z0-9_.@:/+-]*$ ]]; then printf '%s' "${v}"
    elif [[ "${v}" != *"'"* ]]; then printf "'%s'" "${v}"
    else v="${v//\\/\\\\}"; v="${v//\"/\\\"}"; v="${v//\$/\\\$}"; printf '"%s"' "${v}"
    fi
}

mark_done() { echo "$1 $(date '+%F %T')" >> "${STATE}"; }
is_done()   { [[ -f "${STATE}" ]] && grep -q "^$1 " "${STATE}"; }
done_when() { [[ -f "${STATE}" ]] && grep "^$1 " "${STATE}" | tail -1 | cut -d' ' -f2-; }

check_result() {  # check_result PASS|FAIL|WARN "text"
    printf '%s\t%s\n' "$1" "$2" >> "${CHECKS}"
    case "$1" in PASS) ok "$2" ;; WARN) warn "$2" ;; *) bad "$2" ;; esac
}

need_code() {
    [[ -x "${SPU}" || -f "${DOCK}/compose.yaml" ]] || { bad "The website code is not downloaded yet. Run step 3 first."; return 1; }
}

wait_healthy() {  # wait_healthy service [seconds]
    local svc="$1" max="${2:-300}" t=0 id st
    while (( t < max )); do
        id="$("${SPU}" ps -q "${svc}" 2>/dev/null | head -1)"
        if [[ -n "${id}" ]]; then
            st="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "${id}" 2>/dev/null)"
            [[ "${st}" == healthy ]] && return 0
            [[ "${st}" == running ]] && ! docker inspect -f '{{if .State.Health}}x{{end}}' "${id}" | grep -q x && return 0
        fi
        sleep 5; t=$((t + 5))
    done
    return 1
}

spu_artisan() { "${SPU}" exec -T web spu-entrypoint artisan "$@"; }

# ── the old server over HTTPS (cPanel's own pages; read-only) ─────────────────
CPANEL_PASS="${CPANEL_PASS:-}"
cw() {
    CPANEL_URL="${CPANEL_URL}" CPANEL_USER="${CPANEL_USER}" CPANEL_PASSWORD="${CPANEL_PASS}" \
        python3 "${DOCK}/scripts/cpanel-web.py" "$@"
}
ensure_cpanel_login() {
    local tries=0 out rc
    [[ -n "${CPANEL_PASS}" ]] && return 0
    info "The cPanel password is the one you use to log in at ${CPANEL_URL}."
    info "It is kept in memory only and forgotten when the script ends."
    while (( tries < 3 )); do
        ask_secret CPANEL_PASS "cPanel password for user ${CPANEL_USER}"
        out="$(cw check 2>&1)"; rc=$?
        if [[ ${rc} -eq 0 ]]; then ok "Logged in to cPanel (read-only use)."; return 0; fi
        if [[ ${rc} -eq 3 ]]; then
            bad "This cPanel account asks for a two-factor code, which this script cannot use."
            CPANEL_PASS=""; return 1
        fi
        bad "${out}"
        CPANEL_PASS=""; tries=$((tries + 1))
        (( tries < 3 )) && info "Try again (after 3 wrong tries the host may block this server for a while)."
    done
    return 1
}

# ── steps ──────────────────────────────────────────────────────────────────────

step_prereqs() {
    # shellcheck disable=SC1091
    . /etc/os-release 2>/dev/null || true
    info "System: ${PRETTY_NAME:-unknown}"
    [[ "${ID:-}" == ubuntu || "${ID_LIKE:-}" == *debian* ]] || warn "This script was written for Ubuntu. Continuing anyway."

    local cpus ram_gb free_srv free_root
    cpus="$(nproc)"
    ram_gb="$(awk '/MemTotal/{printf "%d", $2/1048576 + 0.5}' /proc/meminfo)"
    mkdir -p /srv
    free_srv="$(df -BG --output=avail /srv | tail -1 | tr -dc 0-9)"
    free_root="$(df -BG --output=avail /var/lib 2>/dev/null | tail -1 | tr -dc 0-9)"
    info "CPU cores: ${cpus}   RAM: ${ram_gb} GB   Free disk: ${free_srv} GB in /srv, ${free_root} GB for Docker"
    (( ram_gb >= 4 )) && ok "RAM is enough." || warn "Less than 4 GB RAM. It can work, but it will be slow."
    if (( free_srv < 60 )); then
        bad "Less than 60 GB free in /srv. The old media alone is about 20 GB, plus databases and backups."
        yesno "Continue anyway?" N || exit 1
    elif (( free_srv < 100 )); then
        warn "Less than 100 GB free. Enough to start; backups will need more room later."
    else
        ok "Disk space is enough."
    fi
    conf_set RAM_GB "${ram_gb}"

    info "Installing the tools the scripts use (git, lftp, rsync, python3, tmux, dig, firewall)..."
    export DEBIAN_FRONTEND=noninteractive
    apt-get -o DPkg::Lock::Timeout=600 update -qq >/dev/null 2>&1 || warn "apt-get update reported a problem (continuing)."
    if apt-get -o DPkg::Lock::Timeout=600 install -y -qq git curl lftp rsync gzip python3 ufw openssl tmux dnsutils ca-certificates >/dev/null 2>&1; then
        ok "Tools installed."
    else
        bad "Could not install the tools. Check the internet connection of this server and run this step again."
        return 1
    fi

    if ! command -v docker >/dev/null 2>&1; then
        info "Docker is not installed. Installing it..."
        apt-get -o DPkg::Lock::Timeout=600 install -y -qq docker.io docker-compose-v2 docker-buildx >/dev/null 2>&1 \
            || { info "Ubuntu's Docker packages failed; trying Docker's own installer..."; curl -fsSL https://get.docker.com | sh; }
    fi
    systemctl enable --now docker >/dev/null 2>&1 || true
    if ! docker info >/dev/null 2>&1; then bad "Docker is installed but not running (systemctl status docker)."; return 1; fi
    if ! docker compose version >/dev/null 2>&1; then
        apt-get -o DPkg::Lock::Timeout=600 install -y -qq docker-compose-v2 >/dev/null 2>&1 \
            || apt-get -o DPkg::Lock::Timeout=600 install -y -qq docker-compose-plugin >/dev/null 2>&1 || true
    fi
    docker compose version >/dev/null 2>&1 || { bad "The 'docker compose' plugin is missing."; return 1; }
    docker buildx version >/dev/null 2>&1 || apt-get -o DPkg::Lock::Timeout=600 install -y -qq docker-buildx >/dev/null 2>&1 || true
    ok "Docker $(docker version --format '{{.Server.Version}}' 2>/dev/null) and $(docker compose version --short 2>/dev/null | sed 's/^/compose /')"

    info "Checking that this server can reach what the build needs..."
    local url code fails=0
    for url in https://github.com https://registry-1.docker.io/v2/ https://repo.packagist.org/packages.json https://deb.debian.org/debian/ https://cpanel.spu.edu.sy/; do
        code="$(curl -s -o /dev/null -m 25 -w '%{http_code}' "${url}" || true)"
        if [[ "${code}" =~ ^[1-5][0-9][0-9]$ ]]; then ok "${url%%/v2/} reachable"; else bad "${url} not reachable"; fails=$((fails + 1)); fi
    done
    (( fails == 0 )) || warn "Some addresses are not reachable. The build or the copy may fail; check this server's internet access."

    local busy
    busy="$(ss -ltnpH '( sport = :80 or sport = :443 )' 2>/dev/null | grep -v docker-proxy || true)"
    if [[ -n "${busy}" ]]; then
        warn "Another program already uses port 80 or 443 on this server:"
        printf '%s\n' "${busy}" | sed 's/^/        /'
        local svc
        for svc in apache2 nginx httpd lighttpd caddy; do
            if systemctl is-active --quiet "${svc}" 2>/dev/null; then
                if yesno "Stop and disable '${svc}' so the website can use these ports?" Y; then
                    systemctl disable --now "${svc}" >/dev/null 2>&1 && ok "${svc} stopped."
                fi
            fi
        done
    else
        ok "Ports 80 and 443 are free."
    fi
}

step_settings() {
    say ""
    info "Press Enter to accept the value in [brackets]."
    say ""
    ask DOMAIN "Main address of the website (no www)" "spu.edu.sy"
    DOMAIN="${DOMAIN#http://}"; DOMAIN="${DOMAIN#https://}"; DOMAIN="${DOMAIN%%/*}"; conf_set DOMAIN "${DOMAIN}"
    local alias_def="www.${DOMAIN}"
    [[ "${DOMAIN}" == spu.edu.sy ]] && alias_def="www.spu.edu.sy v2.spu.edu.sy"
    ask ALIASES "Other names that must redirect (301) to it, separated by spaces" "${alias_def}"
    if [[ "${DOMAIN}" != spu.edu.sy ]]; then
        warn "The redirect proof was recorded for spu.edu.sy. With another main address the check in step 13 compares paths only."
    fi

    local detected
    detected="$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for (i=1;i<NF;i++) if ($i=="src") print $(i+1)}')"
    if [[ "${detected}" =~ ^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.) ]]; then
        warn "This server's own address (${detected}) is private. Type the PUBLIC IP from the VPS provider's panel."
        detected=""
    fi
    ask VPS_IP "Public IP address of THIS new server" "${detected}"
    ask OLD_IP "IP address of the OLD server" "${OLD_IP_DEFAULT}"

    say ""
    info "Old server (cPanel). It is only read from, never changed."
    ask CPANEL_URL "cPanel address" "https://cpanel.spu.edu.sy"
    CPANEL_URL="${CPANEL_URL%/}"; conf_set CPANEL_URL "${CPANEL_URL}"
    ask CPANEL_USER "cPanel user name" "spuedu"
    say ""
    info "How can this server read the old one?"
    info "  api = a cPanel API token over port 2083 (use this when FTP and the cPanel login page are closed)"
    info "  ftp = FTP, or the cPanel password over HTTPS"
    while :; do
        ask ACCESS_METHOD "Type api or ftp" "api"
        [[ "${ACCESS_METHOD}" == api || "${ACCESS_METHOD}" == ftp ]] && break
        ACCESS_METHOD=""
    done
    if [[ "${ACCESS_METHOD}" == api ]]; then
        settings_api
    else
        settings_ftp
    fi
    dns_look
}

settings_api() {
    ask CPANEL_API_HOST "cPanel API address (host:port)" "${DOMAIN}:2083"
    CPANEL_API_HOST="${CPANEL_API_HOST#https://}"; CPANEL_API_HOST="${CPANEL_API_HOST%%/*}"
    [[ "${CPANEL_API_HOST}" == *:* ]] || CPANEL_API_HOST="${CPANEL_API_HOST}:2083"
    conf_set CPANEL_API_HOST "${CPANEL_API_HOST}"
    ask CPANEL_REPO "v2's repository in cPanel > Git Version Control" "/home/${CPANEL_USER}/repositories/spu-v2"
    ask CPANEL_BRANCH "Its branch" "dev"
    local have=""
    [[ -f "${API_ENV}" ]] && have="$(. "${API_ENV}"; printf '%s' "${CPANEL_TOKEN:-}")"
    if [[ -n "${have}" ]] && yesno "A cPanel API token is already saved. Keep it?" Y; then
        CPANEL_TOKEN="${have}"
    else
        info "Make one in cPanel > Security > Manage API Tokens (it can be deleted there after the move)."
        ask_secret CPANEL_TOKEN "cPanel API token"
    fi
    write_api_env
    ok "API token saved in ${API_ENV} (root only)."
}

write_api_env() {
    mkdir -p "${SECRETS}"; chmod 700 "${SECRETS}"
    ( umask 077; printf 'CPANEL_TOKEN=%q\n' "${CPANEL_TOKEN}" > "${API_ENV}" )
}

settings_ftp() {
    ask FTP_HOST "FTP server of the old host" "ftp.${DOMAIN}"
    ask FTP_USER "FTP account (its folder must be the whole account, /home/${CPANEL_USER})" "it@${DOMAIN}"
    local have_pw=""
    [[ -f "${SECRETS}/ftp.env" ]] && have_pw="$(. "${SECRETS}/ftp.env"; printf '%s' "${FTP_PASSWORD:-}")"
    if [[ -n "${have_pw}" ]] && yesno "An FTP password is already saved. Keep it?" Y; then
        FTP_PASSWORD="${have_pw}"
    else
        ask_secret FTP_PASSWORD "FTP password for ${FTP_USER}"
    fi
    write_ftp_env "${FTP_VERIFY_CERT:-yes}"
    ok "FTP details saved in ${SECRETS}/ftp.env (root only)."
}

dns_look() {
    say ""
    info "DNS today (read-only look, nothing is changed):"
    local ns ans
    ns="$(dig +short NS "${DOMAIN}" 2>/dev/null | head -1)"
    ans="$(dig +noall +answer ${ns:+@${ns}} "${DOMAIN}" A 2>/dev/null | head -1)"
    if [[ -n "${ans}" ]]; then
        info "  ${ans}   (name server ${ns:-default})"
        local ttl; ttl="$(awk '{print $2}' <<< "${ans}")"
        if [[ "${ttl}" =~ ^[0-9]+$ ]] && (( ttl > 600 )); then
            warn "The DNS cache time (TTL) is ${ttl} seconds ($((ttl / 3600)) h). Recommended: in cPanel > Zone Editor,"
            warn "lower the TTL of ${DOMAIN}, www, v2 and www.v2 to 300 now, and switch at least $((ttl / 3600)) h later."
            warn "Then the switch and any rollback take 5 minutes instead of hours. (The script never edits DNS.)"
        else
            ok "TTL is short (${ttl}s); switching and rolling back will be quick."
        fi
    else
        warn "Could not read the DNS records (dig). Not a problem for now."
    fi
}

write_ftp_env() {
    mkdir -p "${SECRETS}"; chmod 700 "${SECRETS}"
    ( umask 077
      { printf 'FTP_HOST=%q\n' "${FTP_HOST}"
        printf 'FTP_USER=%q\n' "${FTP_USER}"
        printf 'FTP_PASSWORD=%q\n' "${FTP_PASSWORD}"
        printf 'FTP_VERIFY_CERT=%q\n' "$1"
      } > "${SECRETS}/ftp.env" )
    FTP_VERIFY_CERT="$1"; conf_set FTP_VERIFY_CERT "$1"
}

step_code() {
    local url="${REPO_URL:-${REPO_URL_DEFAULT}}"
    ask REPO_URL "Code repository" "${url}"
    mkdir -p "${ROOT}"
    local cred="${SECRETS}/git-credentials"
    if [[ ! -d "${SRC}/.git" ]]; then
        info "Downloading the code..."
        if ! GIT_TERMINAL_PROMPT=0 git clone -q "${REPO_URL}" "${SRC}" 2>/dev/null; then
            rm -rf "${SRC}"
            warn "The repository is private, so GitHub needs a login."
            info "Use a GitHub token with read access to the code: github.com > Settings > Developer settings >"
            info "Personal access tokens > Fine-grained > only this repository > Contents: Read-only."
            local gh_user gh_token
            readin -p "   GitHub user name: " gh_user || true
            ask_secret gh_token "GitHub token"
            ( umask 077; printf 'https://%s:%s@github.com\n' "${gh_user}" "${gh_token}" > "${cred}" )
            if ! GIT_TERMINAL_PROMPT=0 git -c credential.helper="store --file=${cred}" clone -q "${REPO_URL}" "${SRC}"; then
                rm -rf "${SRC}"; bad "Could not download the code with that token."; return 1
            fi
            git -C "${SRC}" config credential.helper "store --file=${cred}"
            ok "Token saved in ${cred} (root only) so updates work later."
        fi
    fi
    # File modes are not ours to track here (chmod below must not block later checkouts).
    git -C "${SRC}" config core.fileMode false
    git -C "${SRC}" fetch -q origin '+refs/heads/*:refs/remotes/origin/*' || { bad "Could not update the code from GitHub."; return 1; }

    # Which branch holds this migration kit: dev once the pull requests are merged.
    local auto="" b
    if git -C "${SRC}" cat-file -e "origin/dev:deploy/docker/migrate.sh" 2>/dev/null; then auto="dev"
    else
        for b in "${MIGRATION_BRANCHES[@]}"; do
            git -C "${SRC}" cat-file -e "origin/${b}:deploy/docker/compose.yaml" 2>/dev/null && { auto="${b}"; break; }
        done
    fi
    [[ -n "${auto}" ]] || { bad "No branch with deploy/docker was found."; return 1; }
    [[ -n "${BRANCH:-}" ]] && ! git -C "${SRC}" rev-parse -q --verify "origin/${BRANCH}" >/dev/null && BRANCH=""
    if [[ -n "${BRANCH:-}" && "${BRANCH}" != "${auto}" && "${auto}" == dev ]]; then
        info "The migration kit is now on dev (the pull requests were merged)."
        BRANCH=""
    fi
    ask BRANCH "Branch to use" "${auto}"
    git -C "${SRC}" checkout -q --force --detach "origin/${BRANCH}" || { bad "Could not switch to ${BRANCH}."; return 1; }
    chmod +x "${DOCK}"/scripts/*.sh "${DOCK}"/scripts/*.py "${DOCK}/bin/spu" "${DOCK}/migrate.sh" 2>/dev/null || true
    [[ -f "${DOCK}/compose.yaml" ]] || { bad "This branch has no deploy/docker/compose.yaml."; return 1; }
    ok "Code ready: ${BRANCH} at $(git -C "${SRC}" log -1 --format='%h (%cd) %s' --date=short)"
    if [[ "${SELF}" != "${DOCK}/migrate.sh" ]] && ! cmp -s "${SELF}" "${DOCK}/migrate.sh"; then
        info "Note: a newer copy of this script came with the code. From now on run:"
        info "  sudo bash ${DOCK}/migrate.sh"
    fi
}

step_prepare() {
    need_code || return 1
    mkdir -p "${DATA}"/{storage,web,legacy,legacy-apps,mysql,caddy,certs} "${ROOT}"/{backups,secrets,dumps,parity}
    chmod 700 "${SECRETS}" "${ROOT}/backups" "${ROOT}/dumps" "${DATA}/certs"
    chown 33:33 "${DATA}/storage" "${DATA}/web" "${DATA}/legacy-apps"
    ln -sfn "${DOCK}/bin/spu" /usr/local/bin/spu
    ok "Folders under ${ROOT} are ready. The command 'spu' controls the website (spu ps, spu logs -f web)."

    local stack="${ROOT}/stack.env"
    if [[ ! -f "${stack}" ]]; then
        ( umask 077
          sed -e "s|^DB_PASSWORD=$|DB_PASSWORD=$(openssl rand -hex 24)|" \
              -e "s|^DB_ROOT_PASSWORD=$|DB_ROOT_PASSWORD=$(openssl rand -hex 24)|" \
              "${DOCK}/env/stack.env.example" > "${stack}" )
        ok "Created ${stack} with new random database passwords."
    fi
    chmod 600 "${stack}"
    local ram="${RAM_GB:-4}" pool workers
    if   (( ram >= 16 )); then pool=4G
    elif (( ram >= 8 ));  then pool=2G
    else pool=1G; fi
    workers=$(( ram * 8 )); (( workers < 20 )) && workers=20; (( workers > 80 )) && workers=80
    stack_set SPU_CANONICAL_HOST "${DOMAIN}"
    stack_set SPU_ALIAS_HOSTS "${ALIASES}"
    stack_set INNODB_BUFFER_POOL_SIZE "${pool}"
    stack_set APACHE_MAX_WORKERS "${workers}"
    stack_set SPU_LEGACY_ORIGIN "https://${OLD_IP}"
    ok "Stack settings: ${DOMAIN} (+ ${ALIASES}), database memory ${pool}, ${workers} PHP workers."

    if ufw status 2>/dev/null | grep -q "Status: active"; then
        ok "The firewall (ufw) is already on."
        ufw allow 80/tcp >/dev/null; ufw allow 443/tcp >/dev/null
    elif yesno "Turn on the firewall? It keeps SSH open and allows only the web ports 80 and 443." Y; then
        local ports p
        ports="$(sshd -T 2>/dev/null | awk '$1=="port"{print $2}')"
        for p in ${ports:-22}; do ufw allow "${p}/tcp" >/dev/null; done
        ufw allow 80/tcp >/dev/null; ufw allow 443/tcp >/dev/null
        ufw --force enable >/dev/null && ok "Firewall on (SSH port ${ports:-22}, 80, 443)."
    else
        info "Firewall left as it is."
    fi
}

ftp_try() {  # ftp_try yes|no   -> 0 when the account's .env can be listed
    LFTP_PASSWORD="${FTP_PASSWORD}" timeout 120 lftp -c "
set ftp:ssl-force true
set ftp:ssl-protect-data true
set ssl:verify-certificate $1
set net:max-retries 2
set net:timeout 25
open --env-password -u '${FTP_USER}' ftp://${FTP_HOST}
cls -1 spu_v2_app/.env
" >/dev/null 2>"${ROOT}/parity/ftp-test.err"
}

step_connect() {
    need_code || return 1
    if [[ -z "${ACCESS_METHOD:-}" ]]; then  # step 2 was answered by an older version of this script
        info "How can this server read the old one?"
        info "  api = a cPanel API token over port 2083 (use this when FTP and the cPanel login page are closed)"
        info "  ftp = FTP, or the cPanel password over HTTPS"
        while :; do
            ask ACCESS_METHOD "Type api or ftp" "api"
            [[ "${ACCESS_METHOD}" == api || "${ACCESS_METHOD}" == ftp ]] && break
            ACCESS_METHOD=""
        done
        [[ "${ACCESS_METHOD}" == api ]] && settings_api
    fi
    [[ "${ACCESS_METHOD}" == api ]] && { step_connect_api; return $?; }
    [[ -f "${SECRETS}/ftp.env" ]] && FTP_PASSWORD="$(. "${SECRETS}/ftp.env"; printf '%s' "${FTP_PASSWORD:-}")"
    mkdir -p "${ROOT}/parity"
    info "1) Files: FTP over TLS to ${FTP_HOST} as ${FTP_USER}"
    local ftp_ok=no
    while :; do
        if timeout 15 bash -c "exec 9<>/dev/tcp/${FTP_HOST}/21" 2>/dev/null; then
            ok "Port 21 on ${FTP_HOST} answers."
            if ftp_try yes; then ftp_ok=yes; write_ftp_env yes
            elif ftp_try no; then ftp_ok=yes; write_ftp_env no
                warn "FTP works, but the old host's FTP certificate is not trusted; using it anyway (traffic is still encrypted)."
            fi
        else
            bad "Port 21 on ${FTP_HOST} does not answer from this server."
        fi
        if [[ "${ftp_ok}" == yes ]]; then ok "FTP login works and the account's files are visible."; break; fi
        [[ -s "${ROOT}/parity/ftp-test.err" ]] && sed 's/^/        /' "${ROOT}/parity/ftp-test.err" | tail -4
        say ""
        info "What now?"
        info "  r = type the FTP host, user or password again and retry"
        info "  h = copy the files over HTTPS with the cPanel password instead (slower, but works without FTP)"
        local a; readin -p "   Choose r or h: " a || a=h
        if [[ "${a}" == r* ]]; then
            ask FTP_HOST "FTP server" "${FTP_HOST}"
            ask FTP_USER "FTP account" "${FTP_USER}"
            ask_secret FTP_PASSWORD "FTP password for ${FTP_USER}"
            write_ftp_env yes
            continue
        fi
        break
    done
    FILE_METHOD=ftp; [[ "${ftp_ok}" == yes ]] || FILE_METHOD=https
    conf_set FILE_METHOD "${FILE_METHOD}"

    say ""
    info "2) Databases and the HTTPS certificate"
    info "cPanel can give a database backup and the installed certificate only to someone logged in."
    info "With the cPanel password the script downloads them for you (read-only: nothing changes on cPanel)."
    info "Without it, you download the database backups in your browser and upload them here yourself."
    if [[ "${FILE_METHOD}" == https ]] || yesno "Let the script download the databases with the cPanel password?" Y; then
        if ensure_cpanel_login; then DB_METHOD=auto
        else
            DB_METHOD=manual
            if [[ "${FILE_METHOD}" == https ]]; then
                bad "Neither FTP nor the cPanel login works, so files cannot be copied. Fix one of them and run this step again."
                conf_set DB_METHOD "${DB_METHOD}"; return 1
            fi
        fi
    else
        DB_METHOD=manual
    fi
    conf_set DB_METHOD "${DB_METHOD}"
    ok "Files will be copied by ${FILE_METHOD^^}; databases: $([[ ${DB_METHOD} == auto ]] && echo 'downloaded by the script' || echo 'you upload them (the script shows how)')."
}

# ── the old server through the cPanel API token (port 2083 only) ──────────────
ca() {  # ca check|export|clean ...   (scripts/cpanel-api.py with the saved token)
    [[ -f "${API_ENV}" ]] || { bad "No API token saved. Run step 2 again."; return 1; }
    ( set -a; . "${API_ENV}"; set +a
      CPANEL_USER="${CPANEL_USER}" CPANEL_HOST="${CPANEL_API_HOST%%:*}" CPANEL_PORT="${CPANEL_API_HOST##*:}" \
      CPANEL_REPO="${CPANEL_REPO}" CPANEL_BRANCH="${CPANEL_BRANCH}" CPANEL_HOME="/home/${CPANEL_USER}" \
      python3 -I "${DOCK}/scripts/cpanel-api.py" "$@" )
}

step_connect_api() {
    mkdir -p "${ROOT}/xfer"; chmod 700 "${ROOT}/xfer"
    info "Old server through the cPanel API at ${CPANEL_API_HOST} as ${CPANEL_USER} (token)."
    while ! ca check; do
        bad "The API does not answer with this token."
        info "  r = type the address or the token again and retry"
        info "  q = stop (fix the token or the firewall, then run this step again)"
        local a; readin -p "   Choose r or q: " a || a=q
        [[ "${a}" == r* ]] || return 1
        settings_api
    done
    ok "The cPanel API answers."
    FILE_METHOD=api; DB_METHOD=api
    conf_set FILE_METHOD api; conf_set DB_METHOD api
    say ""
    info "How the copy works: the old server packs the files and database dumps itself"
    info "(a Git deploy of ${CPANEL_BRANCH} that only exports: nothing on the site changes), and this server"
    info "reads the pieces back through the API, checks every piece, and deletes the copy there afterwards."
    info "The code in ${CPANEL_BRANCH} must contain deploy/v2-staging/vps-export.sh (pushed to GitHub)."
}

API_PKG=""   # this run's package of the old server's .env, storage, runtime files, apps and databases

api_core_items() {
    local items=(env storage web "db:${SITE_DB}") pair
    if [[ "${APPS_MODE:-local}" != remote ]]; then
        items+=(app:forms app:Medicine app:spuh)
        for pair in "${APP_DBS[@]}"; do items+=("db:${pair%%:*}:${pair#*:}"); done
    fi
    printf '%s\n' "${items[@]}"
}

api_core() {  # fetch the package once per run; re-use a recent one outside the final sync
    [[ -n "${API_PKG}" && -f "${API_PKG}/COMPLETE" ]] && return 0
    local items latest age want ok_all=1
    mapfile -t items < <(api_core_items)
    if [[ "${MODE}" != final ]]; then
        latest="$(ls -1dt "${ROOT}"/xfer/core-* 2>/dev/null | head -1)"
        if [[ -n "${latest}" && -f "${latest}/COMPLETE" ]]; then
            age=$(( $(date +%s) - $(stat -c %Y "${latest}/COMPLETE") ))
            for want in env storage web; do
                awk -F'\t' -v k="${want}" '$1==k{f=1} END{exit !f}' "${latest}/INDEX" || ok_all=0
            done
            if (( ok_all && age < 6 * 3600 )); then
                API_PKG="${latest}"
                info "Using the copy packed by the old server $(( age / 60 )) minutes ago."
                return 0
            fi
        fi
    fi
    API_PKG="${ROOT}/xfer/core-$(date +%Y%m%d-%H%M%S)"
    info "Asking the old server to pack: ${items[*]}"
    info "This takes a few minutes up to an hour, depending on the size of storage/."
    if ! ca export "${API_PKG}" "${items[@]}"; then
        bad "The copy through the API failed (see above). Run this step again."
        API_PKG=""; return 1
    fi
    ca clean || warn "The packed copy could not be deleted on the old server (~/vps-export); delete it in File Manager."
    local old
    for old in "${ROOT}"/xfer/core-*; do [[ "${old}" != "${API_PKG}" ]] && rm -rf "${old}"; done
    return 0
}

pkg_file() {  # pkg_file <package dir> <item>  -> path of that item's file, or nothing
    [[ -f "$1/INDEX" ]] && awk -F'\t' -v k="$2" '$1==k{print $2; exit}' "$1/INDEX"
}

api_db_file() {  # api_db_file <database>
    [[ -f "${API_PKG}/INDEX" ]] && awk -F'\t' -v d="db:$1" '$1==d || index($1, d ":")==1 {print $2; exit}' "${API_PKG}/INDEX"
}

untar_into() {  # untar_into <archive> <folder>
    mkdir -p "$2"
    tar --no-same-owner --no-same-permissions -xzf "$1" -C "$2"
}

# Old site media, in batches, so the old account never holds more than one
# batch at a time (it runs close to its disk quota). "item|folder under data/legacy";
# a folder missing on the old server is skipped there.
API_MEDIA_BATCHES=(
    "media:public_html/downloads/files|downloads/files"
    "media:public_html/downloads/files2|downloads/files2"
    "media:public_html/images|images"
    "media-root:public_html/downloads|downloads/root media:public_html/FCKBIH|FCKBIH"
)

pull_api() {  # pull_api site|apps|media
    local rc=0 f s
    case "$1" in
        site)
            api_core || return 1
            f="$(pkg_file "${API_PKG}" env)"
            [[ -n "${f}" ]] || { bad "The old server did not pack the .env."; return 1; }
            install -m 600 -o root -g root "${f}" "${SECRETS}/cpanel.env"
            f="$(pkg_file "${API_PKG}" storage)"
            if [[ -n "${f}" ]]; then untar_into "${f}" "${DATA}/storage" || rc=1
            else bad "The old server did not pack storage/."; rc=1; fi
            f="$(pkg_file "${API_PKG}" web)"
            if [[ -n "${f}" ]]; then untar_into "${f}" "${DATA}/web" || rc=1
            else bad "The old server did not pack the document root's files."; rc=1; fi
            chown -R 33:33 "${DATA}/storage" "${DATA}/web" ;;
        apps)
            api_core || return 1
            for s in forms Medicine spuh; do
                f="$(pkg_file "${API_PKG}" "app:${s}")"
                if [[ -n "${f}" ]]; then untar_into "${f}" "${DATA}/legacy-apps/${s}" || rc=1
                else bad "The old server did not pack /${s}."; rc=1; fi
            done
            chown -R 33:33 "${DATA}/legacy-apps" 2>/dev/null ;;
        media)
            local L="${DATA}/legacy" batch entry item pkg batches=("${API_MEDIA_BATCHES[@]}") last=""
            for s in med dent pharm info petrol admin research hospital dent_clinic alumni clubs; do
                last+=" media:public_html/${s}/images|${s}/images"
            done
            batches+=("${last# }")
            for batch in "${batches[@]}"; do
                local entries=() items=()
                read -r -a entries <<< "${batch}"
                for entry in "${entries[@]}"; do items+=("${entry%%|*}"); done
                say ""
                pkg="${ROOT}/xfer/media-$(date +%s)"
                if ! ca export "${pkg}" "${items[@]}"; then
                    warn "Not copied: ${items[*]}"; rc=1; rm -rf "${pkg}"; continue
                fi
                for entry in "${entries[@]}"; do
                    item="${entry%%|*}"
                    f="$(pkg_file "${pkg}" "${item}")"
                    if [[ -n "${f}" ]]; then untar_into "${f}" "${L}/${entry#*|}" || rc=1
                    else info "Not on the old server: ${item#*:}"; fi
                done
                rm -rf "${pkg}"
            done
            ca clean || warn "The packed copy could not be deleted on the old server (~/vps-export); delete it in File Manager."
            install -m 0644 "${DOCK}/legacy-media.htaccess" "${L}/.htaccess" ;;
    esac
    return ${rc}
}

pull() {  # pull site|apps|media
    if [[ "${FILE_METHOD:-ftp}" == api ]]; then
        pull_api "$1"
        return $?
    fi
    if [[ "${FILE_METHOD:-ftp}" == ftp ]]; then
        bash "${DOCK}/scripts/pull-from-cpanel.sh" "$1"
        return $?
    fi
    ensure_cpanel_login || return 1
    local H="/home/${CPANEL_USER}" rc=0 s
    case "$1" in
        site)
            cw mirror "${H}/spu_v2_app/storage" "${DATA}/storage" --exclude framework/cache/data \
                --exclude framework/views --exclude framework/sessions --exclude framework/down --exclude cutover-backups || rc=1
            cw mirror "${H}/public_html/spu_v2/public" "${DATA}/web" --exclude _legacy --exclude forms \
                --exclude Medicine --exclude spuh --exclude storage --exclude .htaccess --exclude robots.txt \
                --exclude .user.ini --exclude php.ini --exclude cgi-bin --exclude .well-known || rc=1
            ( umask 077; cw getfile "${H}/spu_v2_app/.env" "${SECRETS}/cpanel.env" ) || rc=1
            chmod 600 "${SECRETS}/cpanel.env" 2>/dev/null
            chown -R 33:33 "${DATA}/storage" "${DATA}/web" ;;
        apps)
            for s in forms Medicine spuh; do cw mirror "${H}/public_html/${s}" "${DATA}/legacy-apps/${s}" || rc=1; done
            chown -R 33:33 "${DATA}/legacy-apps" ;;
        media)
            local L="${DATA}/legacy"
            cw mirror "${H}/public_html/downloads/files" "${L}/downloads/files" || rc=1
            cw mirror "${H}/public_html/downloads/files2" "${L}/downloads/files2" || rc=1
            cw mirror "${H}/public_html/downloads" "${L}/downloads/root" --no-recursion \
                --include-ext .pdf --include-ext .doc --include-ext .docx --include-ext .xls --include-ext .xlsx \
                --include-ext .ppt --include-ext .pptx --include-ext .zip --include-ext .rar --include-ext .jpg \
                --include-ext .jpeg --include-ext .png --include-ext .gif || rc=1
            cw mirror "${H}/public_html/images" "${L}/images" || rc=1
            for s in med dent pharm info petrol admin research hospital dent_clinic alumni clubs; do
                cw mirror "${H}/public_html/${s}/images" "${L}/${s}/images" || rc=1
            done
            cw mirror "${H}/public_html/FCKBIH" "${L}/FCKBIH" || rc=1
            install -m 0644 "${DOCK}/legacy-media.htaccess" "${L}/.htaccess" ;;
    esac
    return ${rc}
}

step_site() {
    need_code || return 1
    info "Copying v2's .env, storage/ (uploads, media library) and runtime files. Usually a few minutes."
    if ! pull site; then
        bad "The copy stopped with an error. Run this step again: it continues where it stopped."
        return 1
    fi
    if [[ -s "${SECRETS}/cpanel.env" ]] && grep -q '^APP_KEY=.\+' "${SECRETS}/cpanel.env"; then
        ok "The old .env is here (APP_KEY present). $(du -sh "${DATA}/storage" 2>/dev/null | cut -f1) of storage copied."
    else
        bad "The old .env was not copied or has no APP_KEY."
        return 1
    fi
}

step_apps() {
    need_code || return 1
    info "/forms, /Medicine and /spuh are three older PHP applications that v2 shows at those addresses."
    if [[ "${APPS_MODE:-local}" == remote ]] && ! yesno "They are set to stay on the old server. Copy them now instead?" Y; then
        info "Left on the old server."; return 0
    fi
    if pull apps; then
        APPS_MODE=local; conf_set APPS_MODE local
        stack_set SPU_LEGACY_APPS local
        stack_set COMPOSE_PROFILES legacy-apps
        ok "Copied: $(du -sh "${DATA}/legacy-apps" 2>/dev/null | cut -f1)."
    else
        bad "The copy of the applications failed."
        if yesno "Keep serving them from the old server for now (it must stay on), and continue?" Y; then
            APPS_MODE=remote; conf_set APPS_MODE remote
            stack_set SPU_LEGACY_APPS remote
            stack_set COMPOSE_PROFILES ""
            warn "Run this step again later to copy them."
        else
            return 1
        fi
    fi
}

step_media() {
    need_code || return 1
    info "The old site's images and downloads (downloads/files, files2, images, the faculties' images, FCKBIH)."
    info "About 20 GB. It can take hours; if the connection drops, run the script again and it continues."
    local free; free="$(df -BG --output=avail "${DATA}" | tail -1 | tr -dc 0-9)"
    info "Free space now: ${free} GB."
    (( free < 30 )) && warn "Less than 30 GB free. The copy may fill the disk."
    if [[ "${FILE_METHOD:-}" == api ]]; then
        info "Over the API the old server packs one folder at a time; the largest (downloads/files)"
        info "needs that much free space on the old account too. Serving the media from the old server"
        info "for now (answer n) is fine; this step can be run again later."
    fi
    if [[ "${MODE}" != final ]] && ! yesno "Copy them now?" Y; then
        MEDIA_MODE=remote; conf_set MEDIA_MODE remote
        stack_set SPU_LEGACY_MEDIA remote
        warn "Old images and downloads will be fetched from the old server (${OLD_IP}) until you run this step."
        return 0
    fi
    local start; start=$(date +%s)
    if pull media; then
        MEDIA_MODE=local; conf_set MEDIA_MODE local
        stack_set SPU_LEGACY_MEDIA local
        ok "Old media copied: $(du -sh "${DATA}/legacy" 2>/dev/null | cut -f1) in $(( ($(date +%s) - start) / 60 )) min."
    else
        bad "The copy stopped with an error (often a dropped connection)."
        info "Run this step again: it copies only what is still missing."
        if yesno "Meanwhile serve the old media from the old server, and continue with the next steps?" Y; then
            MEDIA_MODE=remote; conf_set MEDIA_MODE remote
            stack_set SPU_LEGACY_MEDIA remote
            return 0
        fi
        return 1
    fi
}

step_appenv() {
    need_code || return 1
    [[ -s "${SECRETS}/cpanel.env" ]] || { bad "The old .env is missing. Run step 6 first."; return 1; }
    local app="${ROOT}/app.env"
    bash "${DOCK}/scripts/make-app-env.sh" "${SECRETS}/cpanel.env" "${app}" "${ROOT}/stack.env" || { bad "Could not build app.env."; return 1; }
    if [[ "${DOMAIN}" != spu.edu.sy ]]; then
        env_set "${app}" APP_URL "https://${DOMAIN}"
        env_set "${app}" APP_CANONICAL_URL "https://${DOMAIN}"
        env_set "${app}" SESSION_DOMAIN "${DOMAIN}"
    fi

    say ""
    info "HTTPS on the other subdomains (rooms, webmail, my, erp, portal, ...)."
    info "If the main site tells browsers 'always use HTTPS, also for subdomains', any subdomain"
    info "that works only with http:// stops opening for visitors. If unsure, answer n (safe)."
    local max_age; max_age="$(env_get "${app}" HSTS_MAX_AGE)"; max_age="${max_age:-604800}"
    if yesno "Do ALL spu.edu.sy subdomains work with https:// ?" N; then
        env_set "${app}" HSTS_INCLUDE_SUBDOMAINS true
        stack_set SPU_HSTS "max-age=${max_age}; includeSubDomains"
    else
        env_set "${app}" HSTS_INCLUDE_SUBDOMAINS false
        stack_set SPU_HSTS "max-age=${max_age}"
    fi

    review_mail "${app}"
    if [[ -s "${SECRETS}/mail.override" ]]; then
        local k v
        while IFS='=' read -r k v; do [[ -n "${k}" ]] && env_set "${app}" "${k}" "${v}"; done < "${SECRETS}/mail.override"
    fi
    chmod 600 "${app}"; chown 33:33 "${app}"
    ok "Website settings ready: ${app}"
}

review_mail() {
    local app="$1" mailer host port user from scheme risky=""
    mailer="$(env_get "${app}" MAIL_MAILER)"; host="$(env_get "${app}" MAIL_HOST)"; port="$(env_get "${app}" MAIL_PORT)"
    user="$(env_get "${app}" MAIL_USERNAME)"; from="$(env_get "${app}" MAIL_FROM_ADDRESS)"
    scheme="$(env_get "${app}" MAIL_SCHEME)"; [[ -z "${scheme}" || "${scheme}" == null ]] && scheme="$(env_get "${app}" MAIL_ENCRYPTION)"
    if [[ -s "${SECRETS}/mail.override" ]]; then
        host="$(env_get "${SECRETS}/mail.override" MAIL_HOST)"; port="$(env_get "${SECRETS}/mail.override" MAIL_PORT)"
        user="$(env_get "${SECRETS}/mail.override" MAIL_USERNAME)"; mailer=smtp
        scheme="$(env_get "${SECRETS}/mail.override" MAIL_SCHEME)"; from="$(env_get "${SECRETS}/mail.override" MAIL_FROM_ADDRESS)"
    fi
    say ""
    info "E-mail the website sends (contact form, form notifications), as set on the old server:"
    info "  mailer: ${mailer:-?}   server: ${host:-?}   port: ${port:-?}   security: ${scheme:-none}"
    info "  login: ${user:-none}   sender: ${from:-?}"
    case "${mailer}" in sendmail|mail|log|array) risky="it uses '${mailer}', which does not send real mail from this server" ;; esac
    case "${host}" in localhost|127.0.0.1|::1) risky="the server is 'localhost', meaning the OLD server's own mail program, which is not here" ;; esac
    [[ "${host}" == "${DOMAIN}" || " ${ALIASES} " == *" ${host} "* ]] && risky="the server is ${host}, which will point to THIS server after the DNS switch"
    if [[ -n "${risky}" ]]; then
        warn "This will not work on the new server: ${risky}."
        info "Use a real mail server instead, e.g. mail.${DOMAIN} (the old host's mail, its DNS record stays) or your mail provider's SMTP."
    fi
    if [[ -z "${risky}" ]] && yesno "Keep these e-mail settings?" Y; then
        return 0
    fi
    yesno "Enter new e-mail (SMTP) settings now?" Y || { warn "E-mail settings unchanged."; return 0; }
    local h p s u pw f
    readin -p "   SMTP server (e.g. mail.${DOMAIN}): " h || true
    readin -p "   Port [587]: " p || true; p="${p:-587}"
    readin -p "   Security: tls, ssl or none [$([[ ${p} == 465 ]] && echo ssl || echo tls)]: " s || true
    s="${s:-$([[ ${p} == 465 ]] && echo ssl || echo tls)}"
    readin -p "   Login (usually the full e-mail address, empty for none): " u || true
    pw=""; [[ -n "${u}" ]] && ask_secret pw "Password for ${u}"
    readin -p "   Sender address [${from:-${u}}]: " f || true; f="${f:-${from:-${u}}}"
    ( umask 077
      { echo "MAIL_MAILER=smtp"
        echo "MAIL_HOST=$(dotenv_quote "${h}")"
        echo "MAIL_PORT=${p}"
        echo "MAIL_SCHEME=$([[ ${s} == ssl ]] && echo smtps || echo smtp)"
        echo "MAIL_ENCRYPTION=$([[ ${s} == none ]] && echo null || echo "${s}")"
        echo "MAIL_USERNAME=$(dotenv_quote "${u:-null}")"
        echo "MAIL_PASSWORD=$(dotenv_quote "${pw:-null}")"
        echo "MAIL_FROM_ADDRESS=$(dotenv_quote "${f}")"
      } > "${SECRETS}/mail.override" )
    ok "New e-mail settings saved (${SECRETS}/mail.override). A test e-mail is offered in step 13."
    warn "The domain's SPF record (and DMARC p=reject) may need this server's IP if mail is sent from here directly."
}

detect_app_db_login() {  # detect_app_db_login <app dir> <database>  -> "file<TAB>user<TAB>password"
    python3 "${DOCK}/scripts/find-db-login.py" "$1" "$2"
}

get_dump() {  # get_dump <database>  -> prints the dump path on stdout (messages on stderr)
    local db="$1" out latest a
    mkdir -p "${ROOT}/dumps"; chmod 700 "${ROOT}/dumps"
    if [[ "${DB_METHOD:-manual}" == api ]]; then
        if api_core >&2; then
            out="$(api_db_file "${db}")"
            if [[ -n "${out}" && -f "${out}" ]] && gzip -t "${out}" 2>/dev/null; then
                info "Using the dump of ${db} packed by the old server at $(cat "${API_PKG}/COMPLETE")." >&2
                printf '%s\n' "${out}"; return 0
            fi
        fi
        bad "The old server did not pack ${db}. Falling back to the manual way for it." >&2
    fi
    if [[ "${DB_METHOD:-manual}" == auto ]] && ensure_cpanel_login >&2; then
        out="${ROOT}/dumps/${db}-$(date +%Y%m%d-%H%M).sql.gz"
        info "Downloading ${db} from cPanel..." >&2
        if ( umask 077; cw sqldump "${db}" "${out}" >&2 ) && gzip -t "${out}" 2>/dev/null; then
            printf '%s\n' "${out}"; return 0
        fi
        rm -f "${out}" "${out}.part"
        bad "The automatic download of ${db} failed. Falling back to the manual way for it." >&2
    fi
    {
        say ""
        info "Manual download of ${db}:"
        info "  1. In your browser open ${CPANEL_URL}, log in, then Files > Backup."
        info "  2. Under 'Download a MySQL Database Backup' click ${db} (a file ${db}.sql.gz downloads)."
        info "  3. Upload it to this server into ${ROOT}/dumps/ (for example with WinSCP, or from your computer:"
        info "     scp ${db}.sql.gz root@${VPS_IP:-this-server}:${ROOT}/dumps/ )"
    } >&2
    while :; do
        # shellcheck disable=SC2010
        latest="$(ls -1t "${ROOT}/dumps/${db}"*.sql* 2>/dev/null | grep -v '\.part$' | head -1)"
        readin -p "   Path of the ${db} file${latest:+ [${latest}]} (Enter when uploaded): " a || a=""
        a="${a:-${latest}}"
        if [[ -n "${a}" && -f "${a}" ]]; then
            info "File date: $(date -r "${a}" '+%F %H:%M'), size $(du -h "${a}" | cut -f1)" >&2
            if [[ "${a}" == *.gz ]] && ! gzip -t "${a}" 2>/dev/null; then bad "That file is not a complete .gz file. Upload it again." >&2; continue; fi
            printf '%s\n' "${a}"; return 0
        fi
        info "No file yet. Upload it, then press Enter." >&2
    done
}

dns_points_here() {
    [[ -n "${VPS_IP:-}" && "$(dig +short "${DOMAIN}" A @8.8.8.8 2>/dev/null | tail -1)" == "${VPS_IP}" ]]
}

# After the switch, the new server holds the newest data. Re-loading the old
# databases would throw away what visitors and editors saved here since then.
guard_after_switch() {
    [[ "${GUARD_CONFIRMED:-}" == 1 ]] && return 0
    if is_done after-dns || dns_points_here; then
        say ""
        bad "The DNS switch has already happened: visitors and editors now use THIS server."
        bad "Loading the databases from the old server again would DELETE everything saved here since the switch."
        local a; readin -p "   Type DELETE-NEW-DATA to do it anyway, or press Enter to stop: " a || a=""
        [[ "${a}" == DELETE-NEW-DATA ]] || { info "Stopped. Nothing was changed."; return 1; }
        bash "${DOCK}/scripts/backup.sh" db || return 1
        info "A backup of the current databases was saved in ${ROOT}/backups/db first."
        GUARD_CONFIRMED=1
    fi
    return 0
}

step_databases() {
    need_code || return 1
    guard_after_switch || return 1
    [[ -f "${ROOT}/app.env" ]] || { bad "Run step 9 first (the website settings)."; return 1; }
    info "Starting the database server..."
    "${SPU}" up -d db >/dev/null 2>&1 || "${SPU}" up -d db
    wait_healthy db 300 || { bad "The database server did not start: spu logs db"; return 1; }
    ok "Database server running."
    [[ "${MODE}" == final ]] && warn "Final sync: make sure editors stopped editing and the dumps are fresh (taken now)."

    # Fetch the package here, not inside get_dump's subshell, so it is fetched once.
    if [[ "${DB_METHOD:-manual}" == api ]] && ! api_core; then
        warn "The old server's package did not arrive; you will upload the database files yourself."
        DB_METHOD=manual
    fi
    # Log in here, not inside get_dump's subshell, so the password is asked once.
    if [[ "${DB_METHOD:-manual}" == auto ]] && ! ensure_cpanel_login; then
        warn "No cPanel login; you will upload the database files yourself."
        DB_METHOD=manual
    fi
    local dump
    dump="$(get_dump "${SITE_DB}")" || return 1
    bash "${DOCK}/scripts/import-db.sh" "${dump}" "${SITE_DB}" || { bad "Import of ${SITE_DB} failed."; return 1; }
    local tables
    tables="$(cd "${ROOT}" && set -a && . ./stack.env && set +a && "${SPU}" exec -T -e MYSQL_PWD="${DB_ROOT_PASSWORD}" db mariadb -uroot -N -e \
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${SITE_DB}'" 2>/dev/null)"
    if [[ "${tables:-0}" -gt 50 ]]; then ok "${SITE_DB} loaded (${tables} tables)."
    else bad "${SITE_DB} has only ${tables:-0} tables; the dump looks incomplete."; return 1; fi

    if [[ "${APPS_MODE:-local}" != local ]]; then
        info "The /forms, /Medicine, /spuh applications stay on the old server, so their databases are not needed here."
        return 0
    fi
    local pair db appdir found f u p
    for pair in "${APP_DBS[@]}"; do
        db="${pair%%:*}"; appdir="${DATA}/legacy-apps/${pair#*:}"
        say ""
        info "Database ${db} (used by /${pair#*:})"
        dump="$(get_dump "${db}")" || return 1
        found="$(detect_app_db_login "${appdir}" "${db}")"
        u=""; p=""
        if [[ -n "${found}" ]]; then
            IFS=$'\t' read -r f u p <<< "${found}"
            info "Found the application's database login in ${pair#*:}/${f}: user ${u}, password ${p:0:2}****** (${#p} characters)."
            yesno "Use it?" Y || { u=""; p=""; }
        else
            warn "Could not find the database login in the application's files."
        fi
        if [[ -z "${u}" ]]; then
            info "Open the application's config file (in ${appdir}) and copy its database user and password."
            readin -p "   Database user for ${db} [${db}]: " u || true; u="${u:-${db}}"
            ask_secret p "Database password for ${u}"
        fi
        bash "${DOCK}/scripts/import-db.sh" "${dump}" "${db}" "${u}" <<< "${p}" || { bad "Import of ${db} failed."; return 1; }
        ok "${db} loaded; user ${u} can use it."
    done
}

step_tls() {
    need_code || return 1
    local stack="${ROOT}/stack.env" certs="${DATA}/certs"
    info "HTTPS needs a certificate for ${DOMAIN} and ${ALIASES}."
    info "  1 = copy the certificate the old server uses now (*.spu.edu.sy, valid until 2027-04-02)."
    info "      HTTPS then works the moment DNS moves. Needs the cPanel password.  (recommended)"
    info "  2 = I put the certificate files on this server myself (fullchain + private key)."
    info "  3 = use a test certificate now and a free Let's Encrypt one right after the DNS switch"
    info "      (visitors may see a warning for a few minutes after the switch)."
    local a; readin -p "   Choose 1, 2 or 3 [1]: " a || a=1; a="${a:-1}"
    local tmp; tmp="$(mktemp -d)"; chmod 700 "${tmp}"
    case "${a}" in
        1)
            ensure_cpanel_login || { rm -rf "${tmp}"; warn "No cPanel login; choose 2 or 3."; step_tls; return $?; }
            cw cert "${DOMAIN}" "${tmp}" || { rm -rf "${tmp}"; bad "Could not read the certificate from cPanel."; step_tls; return $?; } ;;
        2)
            local fc pk
            readin -p "   Path of the certificate (with the chain), e.g. fullchain.pem: " fc || true
            readin -p "   Path of the private key, e.g. privkey.pem: " pk || true
            [[ -f "${fc}" && -f "${pk}" ]] || { rm -rf "${tmp}"; bad "File not found."; step_tls; return $?; }
            cat "${fc}" > "${tmp}/fullchain.pem"; cat "${pk}" > "${tmp}/privkey.pem" ;;
        *)
            rm -rf "${tmp}"
            stack_set SPU_TLS_MODE internal
            TLS_MODE=internal; conf_set TLS_MODE internal
            ok "Test certificate for now. After the DNS switch, 'migrate.sh after-dns' turns on Let's Encrypt."
            return 0 ;;
    esac
    if verify_cert "${tmp}/fullchain.pem" "${tmp}/privkey.pem"; then
        install -m 600 "${tmp}/fullchain.pem" "${certs}/fullchain.pem"
        install -m 600 "${tmp}/privkey.pem" "${certs}/privkey.pem"
        rm -rf "${tmp}"
        stack_set SPU_TLS_MODE files
        TLS_MODE=files; conf_set TLS_MODE files
        ok "Certificate installed (${certs}). HTTPS will be valid as soon as DNS points here."
    else
        rm -rf "${tmp}"
        bad "That certificate cannot be used."
        step_tls; return $?
    fi
}

verify_cert() {  # verify_cert fullchain key
    local fc="$1" pk="$2" end names h ok_all=0
    openssl x509 -in "${fc}" -noout >/dev/null 2>&1 || { bad "Not a certificate file."; return 1; }
    if [[ "$(openssl x509 -in "${fc}" -noout -pubkey 2>/dev/null | sha256sum)" != "$(openssl pkey -in "${pk}" -pubout 2>/dev/null | sha256sum)" ]]; then
        bad "The private key does not belong to this certificate."; return 1
    fi
    end="$(openssl x509 -in "${fc}" -noout -enddate | cut -d= -f2)"
    openssl x509 -in "${fc}" -noout -checkend $((14 * 86400)) >/dev/null || { bad "The certificate expires within 14 days (${end})."; return 1; }
    names="$(openssl x509 -in "${fc}" -noout -ext subjectAltName 2>/dev/null | tr ',' '\n' | sed -n 's/.*DNS:\(.*\)/\1/p')"
    for h in ${DOMAIN} ${ALIASES}; do
        if grep -qx "${h}" <<< "${names}" || grep -qx "\*.${h#*.}" <<< "${names}"; then :; else
            bad "The certificate does not cover ${h} (it covers: $(echo ${names}))."; ok_all=1
        fi
    done
    [[ ${ok_all} -eq 0 ]] || return 1
    ok "Certificate covers ${DOMAIN} ${ALIASES}; valid until ${end}."
}

step_start() {
    need_code || return 1
    [[ -f "${ROOT}/app.env" ]] || { bad "Run step 9 first."; return 1; }
    local sha; sha="$(git -C "${SRC}" rev-parse --short HEAD)"
    info "Building the website image (the first time takes 5 to 15 minutes)..."
    if [[ "${MIGRATE_SKIP_BUILD:-}" == 1 ]] && docker image inspect spu-web:current >/dev/null 2>&1; then
        info "Using the image that is already built (MIGRATE_SKIP_BUILD=1)."
    elif ! "${SPU}" build; then
        bad "The build failed. Read the lines above (often a network problem); then run this step again."; return 1
    fi
    docker tag spu-web:current "spu-web:${sha}" >/dev/null 2>&1 || true
    ok "Image built (${sha})."
    "${SPU}" up -d db >/dev/null 2>&1; wait_healthy db 300 || { bad "Database not healthy."; return 1; }
    info "Running the release job: checks, migrations, redirect rules, search index, sitemap, launch checks..."
    if ! "${SPU}" run --rm release; then
        bad "The release job stopped. The message above says why (nothing was sent to visitors)."
        return 1
    fi
    ok "Release job passed (including launch:validate)."
    "${SPU}" up -d --remove-orphans || { bad "Could not start the services."; return 1; }
    info "Waiting for the website to answer..."
    if wait_healthy web 300; then ok "The website is running."
    else bad "The website did not become healthy. See: spu logs --tail 100 web"; return 1; fi
    "${SPU}" ps
    echo "${sha} $(date -u +%FT%TZ) migrate.sh" >> "${ROOT}/deployed.log"
}

# curl through this server, as if DNS already pointed here
RESOLVE=()
build_resolve() {
    local h
    RESOLVE=()
    for h in ${DOMAIN} ${ALIASES}; do RESOLVE+=(--resolve "${h}:443:127.0.0.1" --resolve "${h}:80:127.0.0.1"); done
}
probe() {  # probe URL [live]  -> "status redirect_url"
    if [[ "${2:-}" == live ]]; then
        curl -s -o /dev/null -m 30 -w '%{http_code} %{redirect_url}' "$1"
    else
        curl -sk -o /dev/null -m 30 "${RESOLVE[@]}" -w '%{http_code} %{redirect_url}' "$1"
    fi
}
expect() {  # expect "label" URL status [location] [live]
    local label="$1" url="$2" want="$3" loc="${4:-}" got code where
    got="$(probe "${url}" "${5:-}")"; code="${got%% *}"; where="${got#* }"
    if [[ "${code}" == "${want}" && ( -z "${loc}" || "${where}" == "${loc}" ) ]]; then
        check_result PASS "${label}: ${code}${loc:+ -> ${where}}"
    else
        check_result FAIL "${label}: expected ${want}${loc:+ -> ${loc}}, got ${code:-no answer}${where:+ -> ${where}}"
    fi
}

smoke_tests() {  # smoke_tests [live]
    local live="${1:-}" a f code admin
    expect "Arabic home page" "https://${DOMAIN}/ar" 200 "" "${live}"
    expect "English home page" "https://${DOMAIN}/en" 200 "" "${live}"
    expect "http:// goes to https://" "http://${DOMAIN}/ar?x=1" 301 "https://${DOMAIN}/ar?x=1" "${live}"
    for a in ${ALIASES}; do
        expect "${a} redirects to the main address" "https://${a}/en/news?page=2" 301 "https://${DOMAIN}/en/news?page=2" "${live}"
    done
    expect "Sitemap" "https://${DOMAIN}/sitemap.xml" 200 "" "${live}"
    local robots
    if [[ "${live}" == live ]]; then robots="$(curl -s -m 30 "https://${DOMAIN}/robots.txt")"
    else robots="$(curl -sk -m 30 "${RESOLVE[@]}" "https://${DOMAIN}/robots.txt")"; fi
    if grep -qi "^sitemap: *https://${DOMAIN}/sitemap.xml" <<< "${robots}" && ! grep -qiE '^disallow: */ *$' <<< "${robots}"; then
        check_result PASS "robots.txt lets search engines in and names the sitemap"
    else
        check_result FAIL "robots.txt does not look like the production one"
    fi
    admin="$(env_get "${ROOT}/app.env" ADMIN_PATH)"
    [[ -n "${admin}" ]] && expect "Admin login page" "https://${DOMAIN}/${admin#/}/login" 200 "" "${live}"
    f="$(grep -m1 -E '^/(downloads/files|images)/' "${DOCK}/redirects/extra-paths.txt" 2>/dev/null)"
    [[ -n "${f}" ]] && expect "An old file (${f})" "https://${DOMAIN}${f}" 200 "" "${live}"
    if [[ "${APPS_MODE:-local}" != off ]]; then
        for a in forms Medicine spuh; do
            code="$(probe "https://${DOMAIN}/${a}/" "${live}")"; code="${code%% *}"
            if [[ "${code}" =~ ^(200|301|302|303)$ ]]; then check_result PASS "/${a}/ answers ${code}"
            else check_result FAIL "/${a}/ answers ${code:-nothing}"; fi
        done
    fi
}

parity_check() {
    local dir="${ROOT}/parity" stamp tool="${DOCK}/scripts/redirect-parity.py" base="${DOCK}/redirects/baseline-v2-2026-11-17.tsv"
    stamp="$(date +%Y%m%d-%H%M)"; mkdir -p "${dir}"
    info "Asking this server the 1,552 old addresses recorded on 17 Nov (status code and target)..."
    python3 "${tool}" record --base "https://${DOMAIN}" --connect-to 127.0.0.1 --insecure \
        --paths "${base}" --concurrency 6 --out "${dir}/vps-${stamp}.tsv" || { check_result FAIL "Redirect check could not run"; return; }
    if python3 "${tool}" compare --strict "${base}" "${dir}/vps-${stamp}.tsv" > "${dir}/compare-${stamp}.txt"; then
        check_result PASS "Old links: all 1,552 recorded addresses answer exactly as on the old server"
        return
    fi
    head -1 "${dir}/compare-${stamp}.txt" | sed 's/^/   /'
    # A difference may only mean the content changed since 17 Nov. Ask the old server now.
    python3 - "${base}" "${dir}/vps-${stamp}.tsv" > "${dir}/diff-paths-${stamp}.txt" <<'PY'
import sys
def load(p):
    rows = {}
    with open(p, encoding="utf-8") as fh:
        next(fh, None)
        for line in fh:
            c = line.rstrip("\n").split("\t")
            if len(c) >= 3:
                rows[c[0]] = (c[1], c[2])
    return rows
b, a = load(sys.argv[1]), load(sys.argv[2])
for p in b:
    if p not in a or b[p] != a[p]:
        print(p)
PY
    local n; n="$(grep -c . "${dir}/diff-paths-${stamp}.txt")"
    info "${n} addresses differ from the 17 Nov record. Asking the old server how it answers them today..."
    python3 "${tool}" record --base "https://v2.spu.edu.sy" --connect-to "${OLD_IP}" --insecure \
        --paths "${dir}/diff-paths-${stamp}.txt" --concurrency 2 --out "${dir}/old-now-${stamp}.tsv" 2>/dev/null
    if python3 "${tool}" compare --strict "${dir}/old-now-${stamp}.tsv" "${dir}/vps-${stamp}.tsv" > "${dir}/compare-old-now-${stamp}.txt"; then
        check_result PASS "Old links: ${n} addresses changed since 17 Nov, but the old server answers them the same as this one today"
    else
        check_result FAIL "Old links: some addresses answer differently from the old server (see ${dir}/compare-old-now-${stamp}.txt)"
        sed -n '1,20p' "${dir}/compare-old-now-${stamp}.txt" | sed 's/^/      /'
    fi
}

step_checks() {
    need_code || return 1
    : > "${CHECKS}"
    build_resolve
    info "Checking this server as visitors will see it after the DNS switch (without changing DNS)."
    local svc st
    for svc in db web queue scheduler caddy; do
        if [[ -n "$("${SPU}" ps -q --status running "${svc}" 2>/dev/null)" ]]; then check_result PASS "Service ${svc} is running"
        else check_result FAIL "Service ${svc} is not running (spu logs ${svc})"; fi
    done
    if [[ "${APPS_MODE:-local}" == local ]]; then
        if [[ -n "$("${SPU}" ps -q --status running legacy-apps 2>/dev/null)" ]]; then check_result PASS "Service legacy-apps is running"
        else check_result FAIL "Service legacy-apps is not running"; fi
    fi
    smoke_tests
    if [[ "${TLS_MODE:-internal}" == files ]]; then
        if echo | openssl s_client -connect 127.0.0.1:443 -servername "${DOMAIN}" -verify_return_error >/dev/null 2>&1; then
            check_result PASS "HTTPS certificate is valid for ${DOMAIN}"
        else
            check_result FAIL "HTTPS certificate is not accepted for ${DOMAIN}"
        fi
    else
        check_result WARN "Test HTTPS certificate in use; run 'migrate.sh after-dns' right after the DNS switch"
    fi
    [[ "${MEDIA_MODE:-local}" == remote ]] && check_result WARN "Old images/downloads still come from the old server (step 8 not finished)"
    [[ "${APPS_MODE:-local}" == remote ]] && check_result WARN "/forms, /Medicine, /spuh still run on the old server (step 7)"
    if [[ -n "$(spu_artisan queue:failed 2>/dev/null | grep -E '^[[:space:]]*[0-9a-f-]{8,}' || true)" ]]; then
        check_result WARN "There are failed queue jobs (spu artisan queue:failed)"
    fi

    parity_check

    if [[ "${MODE}" != final ]] && yesno "Also test all 28,428 old sitemap addresses for server errors? (about 20-40 minutes)" N; then
        local out; out="${ROOT}/parity/full-$(date +%Y%m%d-%H%M).tsv"
        python3 "${DOCK}/scripts/redirect-parity.py" record --base "https://${DOMAIN}" --connect-to 127.0.0.1 --insecure \
            --paths "${DOCK}/redirects/old-sitemap-paths.txt.gz" --concurrency 8 --out "${out}"
        local errors; errors="$(awk -F'\t' 'NR>1 && ($2 ~ /^5/ || $2 == "ERR")' "${out}" | wc -l)"
        if (( errors == 0 )); then check_result PASS "All old sitemap addresses answer without server errors"
        else check_result FAIL "${errors} old sitemap addresses gave a server error (see ${out})"; fi
    fi

    if yesno "Send a test e-mail from the website now?" N; then
        local to; readin -p "   Send it to which address: " to || true
        if [[ "${to}" =~ ^[^@[:space:]\'\"\\]+@[^@[:space:]\'\"\\]+$ ]]; then
            if spu_artisan tinker --execute="Illuminate\\Support\\Facades\\Mail::raw('Test from the new SPU server.', function (\$m) { \$m->to('${to}')->subject('SPU new server: mail test'); }); echo 'sent';" 2>&1 | tail -3 | grep -q sent; then
                check_result PASS "Test e-mail handed to the mail server (check the inbox of ${to})"
            else
                check_result FAIL "Test e-mail failed: spu logs web, and the mail settings in step 9"
            fi
        else
            warn "That does not look like an e-mail address; skipped."
        fi
    fi
}

step_report() {
    local fails warns verdict
    [[ -f "${CHECKS}" ]] || { warn "No checks recorded yet. Run step 13 first."; }
    fails="$(grep -c '^FAIL' "${CHECKS}" 2>/dev/null)"; fails="${fails:-0}"
    warns="$(grep -c '^WARN' "${CHECKS}" 2>/dev/null)"; warns="${warns:-0}"
    if [[ ! -f "${CHECKS}" ]]; then verdict="NOT CHECKED YET"
    elif (( fails == 0 )); then verdict="READY: all checks passed"
    else verdict="NOT READY: ${fails} check(s) failed. Do NOT change DNS. Fix them, then run: sudo bash ${DOCK}/migrate.sh step 13"; fi
    {
        line
        echo "SPU website move: result ($(date '+%F %H:%M %Z'))"
        line
        if [[ -f "${CHECKS}" ]]; then
            while IFS=$'\t' read -r r t; do printf '  %-5s %s\n' "${r}" "${t}"; done < "${CHECKS}"
        fi
        echo ""
        echo "  >>> ${verdict}"
        (( warns > 0 )) && echo "      (${warns} warning(s): read them; they do not block the switch)"
        echo ""
        line
        echo "DNS SWITCH (by hand, in the OLD cPanel; this script never changes DNS)"
        line
        cat <<TXT
  Before: lower the TTL of the 4 records below to 300 at least 4 hours earlier
  (cPanel > Domains > Zone Editor > ${DOMAIN} > Manage > Edit > TTL 300).

  1. Tell the editors to stop editing in the admin panel.
  2. On this server:   sudo bash ${DOCK}/migrate.sh final
     It copies the latest files and databases and runs every check again.
     Continue ONLY if it ends with "READY".
  3. In cPanel > Zone Editor > ${DOMAIN} > Manage, edit these A records:
        ${DOMAIN}.            A   ${OLD_IP}  ->  ${VPS_IP}
        www.${DOMAIN}.        A   ${OLD_IP}  ->  ${VPS_IP}   (only if an A record exists; a CNAME follows by itself)
        v2.spu.edu.sy.        A   ${OLD_IP}  ->  ${VPS_IP}
        www.v2.spu.edu.sy.    A   ${OLD_IP}  ->  ${VPS_IP}
     Do NOT touch anything else: MX, mail, webmail, cpanel, ftp, rooms, my, portal,
     erp, ums, new, wmail, and the TXT records (SPF, DKIM, DMARC) stay as they are.
  4. Wait 5 to 10 minutes. Check:   dig +short ${DOMAIN} @8.8.8.8   shows ${VPS_IP}
  5. On this server:   sudo bash ${DOCK}/migrate.sh after-dns
     It checks the live site, turns on HTTPS if needed, and sets up nightly backups.
  6. Google Search Console: property https://${DOMAIN}, submit https://${DOMAIN}/sitemap.xml
TXT
        echo ""
        line
        echo "ROLLBACK (if something is wrong after the switch)"
        line
        cat <<TXT
  The old server was never changed, so going back is only DNS:
  1. Save what visitors sent to the new server meanwhile (form submissions, edits):
        sudo bash ${DOCK}/scripts/backup.sh db
     (files land in ${ROOT}/backups/db/)
  2. In cPanel > Zone Editor set the same 4 records back to   ${OLD_IP}
  3. With TTL 300 visitors are back on the old site within about 5 minutes
     (with TTL 14400, up to 4 hours).
  4. Anything entered on the new server after the switch must be re-entered on the old one
     by hand (it is in the backup from step 1).

  Go back instead of fixing forward when: the home page or admin login fails, many pages
  give errors (5xx), the forms do not work, or e-mail cannot be sent.
TXT
        line
        echo "Useful commands:  spu ps   |  spu logs -f web   |  sudo bash ${DOCK}/migrate.sh status"
        echo "Logs of this script: ${LOGDIR}"
        line
    } | tee "${REPORT}"
    chmod 600 "${REPORT}"
    say "   (Saved in ${REPORT})"
    REPORT_FAILS="${fails}"
    return 0
}

after_dns() {
    need_code || return 1
    : > "${CHECKS}"
    info "Checking that the public DNS points ${DOMAIN} to this server (${VPS_IP})..."
    local h r ok_dns=1
    for h in ${DOMAIN} ${ALIASES}; do
        for r in 8.8.8.8 1.1.1.1; do
            local got; got="$(dig +short "${h}" A @"${r}" 2>/dev/null | tail -1)"
            if [[ "${got}" == "${VPS_IP}" ]]; then ok "${h} -> ${got} (resolver ${r})"
            else warn "${h} -> ${got:-nothing} at ${r} (expected ${VPS_IP})"; ok_dns=0; fi
        done
    done
    if (( ok_dns == 0 )); then
        warn "DNS does not point here everywhere yet. Wait a few minutes (TTL) and run 'migrate.sh after-dns' again."
        yesno "Continue anyway?" N || return 1
    fi
    if [[ "${TLS_MODE:-internal}" == internal ]]; then
        info "Switching HTTPS to Let's Encrypt (free, renews itself)..."
        stack_set SPU_TLS_MODE auto
        TLS_MODE=auto; conf_set TLS_MODE auto
        "${SPU}" up -d --force-recreate caddy
        local t=0
        until curl -s -o /dev/null -m 15 "https://${DOMAIN}/up"; do
            sleep 10; t=$((t + 10))
            (( t > 300 )) && { bad "No valid certificate after 5 minutes: spu logs caddy"; break; }
        done
    fi
    smoke_tests live
    if echo | openssl s_client -connect "${DOMAIN}:443" -servername "${DOMAIN}" -verify_return_error >/dev/null 2>&1; then
        check_result PASS "Visitors get a valid HTTPS certificate"
    else
        check_result FAIL "The HTTPS certificate is not valid for visitors yet (spu logs caddy)"
    fi
    if [[ ! -f /etc/cron.d/spu-backup ]] && yesno "Install the nightly backup (databases + uploads, kept 14 days, 01:30)?" Y; then
        echo "30 1 * * * root ${DOCK}/scripts/backup.sh >> ${ROOT}/backups/backup.log 2>&1" > /etc/cron.d/spu-backup
        chmod 644 /etc/cron.d/spu-backup
        ok "Nightly backup installed. Copy ${ROOT}/backups to another place regularly too."
    fi
    if [[ -f "${SECRETS}/ftp.env" ]] && yesno "Delete the saved FTP password from this server now? (only needed for copying)" Y; then
        shred -u "${SECRETS}/ftp.env" 2>/dev/null || rm -f "${SECRETS}/ftp.env"
        ok "FTP password deleted."
    fi
    if [[ -f "${API_ENV}" ]] && yesno "Delete the saved cPanel API token from this server now? (only needed for copying)" Y; then
        shred -u "${API_ENV}" 2>/dev/null || rm -f "${API_ENV}"
        ok "API token deleted here. Also delete it in cPanel > Security > Manage API Tokens."
    fi
    mark_done after-dns
    step_report
    cat <<TXT

   Next days:
   - Keep the old hosting account: it still holds the DNS zone, e-mail routing, rooms, webmail.
   - Old links that do not resolve:  spu artisan continuity:report-unresolved
   - Errors:                         spu logs --tail 200 web
TXT
}

# ── running steps ──────────────────────────────────────────────────────────────

run_step() {  # run_step <index 0-based>
    local i="$1" id="${STEP_IDS[$1]}" rc
    say ""; line
    printf '%s Step %d of %d: %s%s\n' "${B}${C}" "$((i + 1))" "${#STEP_IDS[@]}" "${STEP_TITLES[$i]}" "${N}"
    line
    "step_${id}"; rc=$?
    if [[ ${rc} -eq 0 ]]; then
        mark_done "${id}"
        ok "Step $((i + 1)) done."
    else
        bad "Step $((i + 1)) did not finish."
        info "Fix what is described above, then run the script again; it starts at this step."
        info "Log: ${LOG}"
    fi
    return ${rc}
}

show_status() {
    local i
    say ""
    for i in "${!STEP_IDS[@]}"; do
        if is_done "${STEP_IDS[$i]}"; then
            printf '   %s[done]%s %2d. %s  (%s)\n' "${G}" "${N}" "$((i + 1))" "${STEP_TITLES[$i]}" "$(done_when "${STEP_IDS[$i]}")"
        else
            printf '   [    ] %2d. %s\n' "$((i + 1))" "${STEP_TITLES[$i]}"
        fi
    done
    say ""
}

walk() {
    local i first=-1
    for i in "${!STEP_IDS[@]}"; do
        is_done "${STEP_IDS[$i]}" || { first=${i}; break; }
    done
    show_status
    if (( first < 0 )); then
        info "Every step is done. Use 'final' at switch time, 'after-dns' after the switch,"
        info "'step <N>' to repeat one step, or 'report' to see the result again."
        return 0
    fi
    if (( first > 0 )); then
        local a
        readin -p "   Continue with step $((first + 1))? Press Enter, or type another step number: " a || a=""
        if [[ "${a}" =~ ^[0-9]+$ ]] && (( a >= 1 && a <= ${#STEP_IDS[@]} )); then first=$((a - 1)); fi
    fi
    for (( i = first; i < ${#STEP_IDS[@]}; i++ )); do
        info "Next: step $((i + 1)), ${STEP_TITLES[$i]}."
        pause
        run_step "${i}" || exit 1
    done
}

final_sync() {
    say ""
    warn "FINAL SYNC. Do this only when you are ready to switch DNS right after."
    info "Before you go on: editors must stop working in the admin panel until the switch is finished."
    info "Form submissions keep arriving on the old server until DNS moves; the copy takes them along."
    yesno "Have the editors stopped?" N || { info "Run 'migrate.sh final' again when they have."; return 0; }
    guard_after_switch || return 1
    local i id
    for id in site apps media appenv databases start checks report; do
        for i in "${!STEP_IDS[@]}"; do [[ "${STEP_IDS[$i]}" == "${id}" ]] && break; done
        [[ "${id}" == apps && "${APPS_MODE:-local}" == remote ]] && continue
        [[ "${id}" == media && "${MEDIA_MODE:-local}" == remote ]] && { warn "Old media is still served from the old server (step 8 never finished)."; continue; }
        # The old site's media no longer changes; over the API a fresh copy would mean packing ~20 GB again.
        [[ "${id}" == media && "${FILE_METHOD:-}" == api ]] && is_done media && { info "Old media was already copied; not copied again."; continue; }
        run_step "${i}" || exit 1
    done
    (( ${REPORT_FAILS:-0} == 0 )) || exit 1
}

# ── main ───────────────────────────────────────────────────────────────────────
MODE="${1:-walk}"
[[ ${EUID} -eq 0 ]] || { echo "Please run with sudo:  sudo bash $0 ${*}" >&2; exit 1; }
case "${MODE}" in walk|final|after-dns|status|step|report|--help|-h|help) ;; *) MODE=help ;; esac
if [[ "${MODE}" == help || "${MODE}" == -h || "${MODE}" == --help ]]; then sed -n '3,25p' "${SELF}"; exit 0; fi

# Long copies must survive a dropped SSH connection: offer to run inside tmux.
if [[ -z "${TMUX:-}" && -z "${MIGRATE_INPUT:-}" && "${MODE}" =~ ^(walk|final|step)$ ]]; then
    command -v tmux >/dev/null 2>&1 || apt-get -o DPkg::Lock::Timeout=300 install -y -qq tmux >/dev/null 2>&1 || true
    if command -v tmux >/dev/null 2>&1; then
        if tmux has-session -t spu-migrate 2>/dev/null; then
            echo "The migration is already running in the background session. Re-opening it..."
            sleep 1; exec tmux attach -t spu-migrate
        fi
        echo ""
        echo "  The copy can take hours. Inside a 'tmux' session it keeps running even if your"
        echo "  SSH connection drops; reconnect and type:  sudo tmux attach -t spu-migrate"
        if yesno "Run inside tmux (recommended)?" Y; then
            exec tmux new-session -s spu-migrate "bash '${SELF}' ${*}; echo; read -r -p 'Finished. Press Enter to close this window. ' _"
        fi
    fi
fi

mkdir -p "${ROOT}" "${LOGDIR}"; chmod 700 "${LOGDIR}"
LOG="${LOGDIR}/migrate-$(date +%Y%m%d-%H%M%S)-${MODE}.log"
exec > >(tee -a "${LOG}") 2>&1
conf_load
say ""
say "${B}SPU website: move to the new server${N}   (script ${VERSION}, mode: ${MODE})"
say "Nothing is changed on the old server, and DNS is never changed by this script."

case "${MODE}" in
    walk)      walk ;;
    final)     final_sync ;;
    after-dns) after_dns ;;
    status)    show_status ;;
    report)    step_report ;;
    step)
        n="${2:-}"
        [[ "${n}" =~ ^[0-9]+$ ]] && (( n >= 1 && n <= ${#STEP_IDS[@]} )) || { show_status; echo "Usage: migrate.sh step <1-${#STEP_IDS[@]}>"; exit 2; }
        run_step "$((n - 1))" ;;
esac

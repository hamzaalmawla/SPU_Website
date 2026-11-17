#!/usr/bin/env bash
#
# Copy what the site needs from the cPanel account to /srv/spu/data, over FTPS.
# Safe to re-run: it only fetches files that are new or changed, so the big
# first copy can run the day before and the final run at cutover takes minutes.
#
#     pull-from-cpanel.sh media   the old site's media (~20 GB): downloads, images, FCKBIH
#     pull-from-cpanel.sh apps    /forms, /Medicine, /spuh (code and their uploads)
#     pull-from-cpanel.sh site    v2's storage/, document root and .env
#     pull-from-cpanel.sh all
#
# Credentials: /srv/spu/secrets/ftp.env with FTP_HOST, FTP_USER, FTP_PASSWORD
# (an FTP account whose root is /home/spuedu, e.g. it@spu.edu.sy). Nothing is
# ever written on the cPanel side.
set -euo pipefail

ROOT="${SPU_ROOT:-/srv/spu}"
DATA="${ROOT}/data"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck disable=SC1091
source "${ROOT}/secrets/ftp.env"
: "${FTP_HOST:?}" "${FTP_USER:?}" "${FTP_PASSWORD:?}"
export LFTP_PASSWORD="${FTP_PASSWORD}"
REMOTE="${FTP_ROOT:-}"   # empty when the FTP account's root is /home/spuedu
SUBSITES=(med dent pharm info petrol admin research hospital dent_clinic alumni clubs)

command -v lftp >/dev/null || { echo "Install lftp first: apt-get install -y lftp" >&2; exit 1; }

run_lftp() {
    lftp -c "
set ftp:ssl-force true
set ftp:ssl-protect-data true
set ssl:verify-certificate ${FTP_VERIFY_CERT:-yes}
set net:max-retries 8
set net:reconnect-interval-base 15
set net:timeout 60
set mirror:parallel-transfer-count ${FTP_PARALLEL:-4}
open --env-password -u '${FTP_USER}' ftp://${FTP_HOST}
$1
"
}

mirror() {  # mirror <remote dir> <local dir> [extra lftp mirror options]
    local from="$1" to="$2"; shift 2
    mkdir -p "${to}"
    printf '\n▸ %s -> %s\n' "${from}" "${to}"
    run_lftp "mirror --only-newer --no-perms --no-umask --verbose=1 $* '${REMOTE}${from}' '${to}'"
}

pull_media() {
    local L="${DATA}/legacy"
    mirror public_html/downloads/files  "${L}/downloads/files"
    mirror public_html/downloads/files2 "${L}/downloads/files2"
    # Loose documents directly in /downloads/ (public/.htaccess serves only these).
    mirror public_html/downloads "${L}/downloads/root" --no-recursion \
        --include-glob '*.pdf' --include-glob '*.PDF' --include-glob '*.doc*' --include-glob '*.xls*' \
        --include-glob '*.ppt*' --include-glob '*.zip' --include-glob '*.rar' --include-glob '*.jp*g' \
        --include-glob '*.JP*G' --include-glob '*.png' --include-glob '*.gif'
    mirror public_html/images "${L}/images"
    for s in "${SUBSITES[@]}"; do mirror "public_html/${s}/images" "${L}/${s}/images"; done
    mirror public_html/FCKBIH "${L}/FCKBIH"
    install -m 0644 "${HERE}/legacy-media.htaccess" "${L}/.htaccess"
    # cv_bank holds applicants' CVs and was deliberately never published by v2.
}

pull_apps() {
    for app in forms Medicine spuh; do
        mirror "public_html/${app}" "${DATA}/legacy-apps/${app}"
    done
    chown -R 33:33 "${DATA}/legacy-apps"
}

pull_site() {
    mirror spu_v2_app/storage "${DATA}/storage" \
        --exclude framework/cache/data/ --exclude framework/views/ --exclude framework/sessions/ \
        --exclude framework/down --exclude cutover-backups/
    # Runtime files of the document root (image derivatives, uploads written
    # next to public images). The release's own files are copied over these at
    # every start; the staging overlays are left behind.
    mirror public_html/spu_v2/public "${DATA}/web" \
        --exclude-glob _legacy --exclude-glob forms --exclude-glob Medicine --exclude-glob spuh \
        --exclude-glob storage --exclude-glob .htaccess --exclude-glob robots.txt \
        --exclude-glob .user.ini --exclude-glob php.ini --exclude-glob cgi-bin --exclude-glob .well-known
    mkdir -p "${ROOT}/secrets"
    run_lftp "get '${REMOTE}spu_v2_app/.env' -o '${ROOT}/secrets/cpanel.env'"
    chmod 600 "${ROOT}/secrets/cpanel.env"
    chown -R 33:33 "${DATA}/storage" "${DATA}/web"
}

case "${1:-}" in
    media) pull_media ;;
    apps)  pull_apps ;;
    site)  pull_site ;;
    all)   pull_site; pull_apps; pull_media ;;
    *) sed -n '3,16p' "$0"; exit 2 ;;
esac
printf '\n✓ done: %s\n' "$*"

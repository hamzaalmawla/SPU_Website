#!/usr/bin/env bash
#
# Packs what the new VPS needs from this cPanel account, for the move to the VPS.
#
# Why this exists: the account has no shell, FTP and the cPanel login page are
# closed to the VPS, cPanel's download pages refuse API tokens, and zipping big
# folders in File Manager fails. What does work with an API token is
#   - Fileman/save_file_content (write a small text file),
#   - VersionControlDeployment/create (run .cpanel.yml, i.e. cpanel-deploy.sh),
#   - Fileman/get_file_content (read a text file).
# So the VPS (deploy/docker/scripts/cpanel-api.py) writes a request file, starts a
# deploy, and cpanel-deploy.sh hands over to this script before doing anything
# else. This script packs each requested item as gzip, cuts it into chunks and
# writes every chunk as base64 text, which the VPS then reads back with
# get_file_content and checks against the sha256 in MANIFEST.
#
#     vps-export.sh <request file>
#
# Request file (key=value lines, written by cpanel-api.py):
#     id=<hex>            export folder name: ~/vps-export/<id>
#     chunk_kb=512        size of one chunk before base64
#     mode=clean          (instead of items) delete every export folder and stop
#     item=env            spu_v2_app/.env
#     item=storage        spu_v2_app/storage (no caches, sessions, logs, cutover backups)
#     item=web            the document root's runtime files (no release-managed files)
#     item=app:<dir>      public_html/<dir>  (forms, Medicine, spuh)
#     item=db:<name>      mysqldump of the v2 database (login from spu_v2_app/.env)
#     item=db:<name>:<dir> mysqldump of an old application's database (login found in public_html/<dir>)
#     item=media:<path>   any folder under the home directory, e.g. public_html/images
#     item=media-root:<path> loose documents and images directly in that folder (not its subfolders)
#
# Only reads the site. Writes nothing outside ~/vps-export/ and never touches
# the database contents, the application or the document root.

set -uo pipefail
umask 077

REQUEST="${1:?request file}"
APP="${SPU_APP_PATH:-/home/spuedu/spu_v2_app}"
HOME_DIR="${SPU_HOME:-$(dirname "${APP}")}"
EXPORTS="${HOME_DIR}/vps-export"
SOURCE="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

say() { printf '%s %s\n' "$(date '+%H:%M:%S')" "$*"; }

req_value() { grep -E "^$1=" "${REQUEST}" | tail -1 | cut -d= -f2- | tr -d '\r'; }

ID="$(req_value id)"
MODE="$(req_value mode)"
CHUNK_KB="$(req_value chunk_kb)"; CHUNK_KB="${CHUNK_KB:-512}"
mapfile -t ITEMS < <(grep -E '^item=' "${REQUEST}" | cut -d= -f2- | tr -d '\r')

mkdir -p "${EXPORTS}"; chmod 700 "${EXPORTS}"

# A request is used once: a normal deploy afterwards deploys normally again.
consume_request() { rm -f "${REQUEST}"; }

if [[ "${MODE}" == clean ]]; then
    consume_request
    say "Deleting every VPS export folder in ${EXPORTS}"
    rm -rf "${EXPORTS:?}"/*
    say "Clean done."
    exit 0
fi

[[ "${ID}" =~ ^[a-f0-9]{8,64}$ ]] || { consume_request; say "Invalid export id '${ID}'."; exit 1; }
[[ "${CHUNK_KB}" =~ ^[0-9]+$ ]] && (( CHUNK_KB >= 64 && CHUNK_KB <= 65536 )) || CHUNK_KB=512
OUT="${EXPORTS}/${ID}"
mkdir -p "${OUT}"
cp "${REQUEST}" "${OUT}/request"
consume_request

# Only one export is kept: the VPS fetches one batch at a time, so the account
# never holds more than one batch (it runs close to its disk quota).
for old in "${EXPORTS}"/*; do
    [[ -d "${old}" && "${old}" != "${OUT}" ]] && rm -rf "${old}"
done

LOG="${OUT}/export.log"
exec > >(tee -a "${LOG}") 2>&1
status() { printf '%s\n' "$*" > "${OUT}/STATUS.tmp" && mv -f "${OUT}/STATUS.tmp" "${OUT}/STATUS"; }

say "VPS export ${ID}: ${#ITEMS[@]} item(s), chunks of ${CHUNK_KB} KB"
status "running starting"

# Split stdin into base64 chunks for one item. Each chunk's sha256 is taken from
# the raw bytes, so the VPS can prove every chunk arrived intact.
chunk_stream() {  # chunk_stream <name>
    local name="$1"
    rm -f "${OUT}/${name}".*
    # shellcheck disable=SC2016
    SHELL=/bin/bash split -a 5 -d -b "${CHUNK_KB}K" \
        --filter='cat > "$FILE" && sha256sum "$FILE" | cut -d" " -f1 > "$FILE.sha" && stat -c %s "$FILE" > "$FILE.len" && base64 "$FILE" > "$FILE.b64.tmp" && mv -f "$FILE.b64.tmp" "$FILE.b64" && rm -f "$FILE"' \
        - "${OUT}/${name}."
}

# One manifest block per item, written when the item is complete.
finish_item() {  # finish_item <name> <file name on the VPS> <kind>
    local name="$1" file="$2" kind="$3" f idx total=0 n=0 block=""
    for f in "${OUT}/${name}".[0-9][0-9][0-9][0-9][0-9].b64; do
        [[ -f "${f}" ]] || continue
        idx="${f%.b64}"; idx="${idx##*.}"
        local sha len
        sha="$(cat "${f%.b64}.sha")"; len="$(cat "${f%.b64}.len")"
        rm -f "${f%.b64}.sha" "${f%.b64}.len"
        block+="chunk	${name}	${idx}	${sha}	${len}	$(basename "${f}")"$'\n'
        total=$((total + len)); n=$((n + 1))
    done
    (( n > 0 )) || return 1
    { printf 'item\t%s\t%s\t%s\t%s\t%s\n' "${name}" "${file}" "${kind}" "${total}" "${n}"; printf '%s' "${block}"; } \
        > "${OUT}/${name}.manifest"
    say "  ${name}: $(numfmt --to=iec "${total}" 2>/dev/null || echo "${total} bytes") in ${n} chunk(s)"
}

# GNU tar exits 1 when a file changed while it was read (uploads, logs): the
# archive is still complete, so only 2 and above are failures.
tar_ok() { local rc="$1"; (( rc <= 1 )); }

pack_dir() {  # pack_dir <name> <dir> [tar exclude patterns...]
    local name="$1" dir="$2"; shift 2
    [[ -d "${dir}" ]] || { say "  ${name}: ${dir} does not exist"; return 1; }
    local ex=() p
    for p in "$@"; do ex+=("--exclude=./${p}"); done
    tar -C "${dir}" --warning=no-file-changed --warning=no-file-removed --ignore-failed-read \
        "${ex[@]}" -czf - . | chunk_stream "${name}"
    local rcs=("${PIPESTATUS[@]}")
    tar_ok "${rcs[0]}" && (( rcs[1] == 0 ))
}

pack_loose_files() {  # pack_loose_files <name> <dir>
    local name="$1" dir="$2"
    [[ -d "${dir}" ]] || { say "  ${name}: ${dir} does not exist"; return 1; }
    ( cd "${dir}" && find . -maxdepth 1 -type f \( -iname '*.pdf' -o -iname '*.doc' -o -iname '*.docx' \
        -o -iname '*.xls' -o -iname '*.xlsx' -o -iname '*.ppt' -o -iname '*.pptx' -o -iname '*.zip' \
        -o -iname '*.rar' -o -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' -o -iname '*.gif' \) -print0 \
      | tar --null -T - -czf - ) | chunk_stream "${name}"
    local rcs=("${PIPESTATUS[@]}")
    tar_ok "${rcs[0]}" && (( rcs[1] == 0 ))
}

env_of() {  # env_of <file> <KEY>
    grep -E "^[[:space:]]*$2=" "$1" | tail -n 1 | cut -d= -f2- | tr -d '\r' | sed -E 's/^"(.*)"$/\1/; s/^'"'"'(.*)'"'"'$/\1/'
}

dump_db() {  # dump_db <name> <database> <user> <password> [host] [port]
    local name="$1" db="$2" user="$3" pass="$4" host="${5:-}" port="${6:-}"
    command -v mysqldump >/dev/null 2>&1 || { say "  mysqldump is not available on this host"; return 1; }
    local cnf; cnf="$(mktemp "${OUT}/.my.XXXXXX")"
    {
        printf '[mysqldump]\nuser=%s\npassword="%s"\n' "${user}" "${pass//\"/\\\"}"
        [[ -n "${host}" ]] && printf 'host=%s\n' "${host}"
        [[ -n "${port}" ]] && printf 'port=%s\n' "${port}"
    } > "${cnf}"
    local tmp="${OUT}/.${name}.sql.gz"
    mysqldump --defaults-extra-file="${cnf}" --single-transaction --quick --routines --triggers \
        --events --default-character-set=utf8mb4 --no-tablespaces "${db}" | gzip -6 > "${tmp}"
    local rcs=("${PIPESTATUS[@]}")
    rm -f "${cnf}"
    if (( rcs[0] != 0 || rcs[1] != 0 )); then say "  mysqldump of ${db} failed"; rm -f "${tmp}"; return 1; fi
    if ! gzip -cd "${tmp}" | tail -c 2000 | grep -q 'Dump completed'; then
        say "  the dump of ${db} has no completion line (truncated)"; rm -f "${tmp}"; return 1
    fi
    say "  ${db}: $(gzip -cd "${tmp}" | grep -c '^CREATE TABLE' || true) tables"
    chunk_stream "${name}" < "${tmp}"
    local rc=$?
    rm -f "${tmp}"
    return ${rc}
}

safe_name() { printf '%s' "$1" | tr -c 'A-Za-z0-9._-' '_'; }

FAILED=()
SKIPPED=()
for item in "${ITEMS[@]}"; do
    [[ -n "${item}" ]] || continue
    kind="${item%%:*}"; arg="${item#*:}"; [[ "${arg}" == "${item}" ]] && arg=""
    name="$(safe_name "${item}")"
    if [[ -f "${OUT}/${name}.manifest" ]]; then say "${item}: already packed"; continue; fi
    say "${item}"
    status "running ${item}"
    ok=1
    case "${kind}" in
        env)
            if [[ -f "${APP}/.env" ]]; then
                chunk_stream "${name}" < "${APP}/.env" && finish_item "${name}" "env" raw || ok=0
            else ok=0; fi ;;
        storage)
            pack_dir "${name}" "${APP}/storage" framework/cache/data framework/views framework/sessions \
                framework/down cutover-backups logs \
                && finish_item "${name}" "storage.tar.gz" tar || ok=0 ;;
        web)
            pack_dir "${name}" "${HOME_DIR}/public_html/spu_v2/public" _legacy forms Medicine spuh storage \
                .htaccess robots.txt .user.ini php.ini cgi-bin .well-known \
                && finish_item "${name}" "web.tar.gz" tar || ok=0 ;;
        app)
            [[ "${arg}" =~ ^[A-Za-z0-9_-]+$ ]] || { say "  bad folder name"; ok=0; }
            (( ok )) && { pack_dir "${name}" "${HOME_DIR}/public_html/${arg}" \
                && finish_item "${name}" "app-${arg}.tar.gz" tar || ok=0; } ;;
        db)
            db="${arg%%:*}"; appdir=""; [[ "${arg}" == *:* ]] && appdir="${arg#*:}"
            [[ "${db}" =~ ^[A-Za-z0-9_]+$ ]] || { say "  bad database name"; ok=0; }
            if (( ok )) && [[ -z "${appdir}" ]]; then
                [[ "$(env_of "${APP}/.env" DB_DATABASE)" == "${db}" ]] || { say "  ${db} is not the database in spu_v2_app/.env"; ok=0; }
                (( ok )) && { dump_db "${name}" "${db}" "$(env_of "${APP}/.env" DB_USERNAME)" "$(env_of "${APP}/.env" DB_PASSWORD)" \
                    "$(env_of "${APP}/.env" DB_HOST)" "$(env_of "${APP}/.env" DB_PORT)" \
                    && finish_item "${name}" "db-${db}.sql.gz" gz || ok=0; }
            elif (( ok )); then
                [[ "${appdir}" =~ ^[A-Za-z0-9_-]+$ ]] || { say "  bad folder name"; ok=0; }
                login=""
                if (( ok )) && command -v python3 >/dev/null 2>&1; then
                    login="$(python3 -I "${SOURCE}/deploy/docker/scripts/find-db-login.py" "${HOME_DIR}/public_html/${appdir}" "${db}" 2>/dev/null || true)"
                fi
                if [[ -z "${login}" ]]; then
                    say "  could not find the login of ${db} in public_html/${appdir} (python3 missing or no config file)"; ok=0
                else
                    IFS=$'\t' read -r _f u p <<< "${login}"
                    dump_db "${name}" "${db}" "${u}" "${p}" && finish_item "${name}" "db-${db}.sql.gz" gz || ok=0
                fi
            fi ;;
        media|media-root)
            [[ "${arg}" =~ ^[A-Za-z0-9_./-]+$ && "${arg}" != *..* ]] || { say "  bad path"; ok=0; }
            if (( ok )) && [[ ! -d "${HOME_DIR}/${arg}" ]]; then
                # Old media folders differ per faculty site: a missing one has nothing to copy.
                say "  not on this server, skipped"; SKIPPED+=("${item}"); continue
            fi
            if (( ok )) && [[ "${kind}" == media ]]; then
                pack_dir "${name}" "${HOME_DIR}/${arg}" && finish_item "${name}" "${name}.tar.gz" tar || ok=0
            elif (( ok )); then
                pack_loose_files "${name}" "${HOME_DIR}/${arg}" && finish_item "${name}" "${name}.tar.gz" tar || ok=0
            fi ;;
        *)
            say "  unknown item"; ok=0 ;;
    esac
    if (( ! ok )); then
        FAILED+=("${item}")
        rm -f "${OUT}/${name}".[0-9][0-9][0-9][0-9][0-9]* "${OUT}/${name}.manifest"
    fi
done

cat "${OUT}"/*.manifest > "${OUT}/MANIFEST.tmp" 2>/dev/null || : > "${OUT}/MANIFEST.tmp"
mv -f "${OUT}/MANIFEST.tmp" "${OUT}/MANIFEST"
(( ${#SKIPPED[@]} )) && say "Skipped (not on this server): ${SKIPPED[*]}"
say "Export folder: $(du -sh "${OUT}" 2>/dev/null | cut -f1)"
if (( ${#FAILED[@]} )); then
    say "NOT PACKED: ${FAILED[*]}"
    if [[ -s "${OUT}/MANIFEST" ]]; then
        # The rest is usable: the VPS takes it and handles the missing items.
        status "partial ${FAILED[*]}"
        exit 0
    fi
    status "failed ${FAILED[*]}"
    exit 1
fi
status "done"
say "Export done."

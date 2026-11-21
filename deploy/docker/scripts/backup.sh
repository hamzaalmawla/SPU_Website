#!/usr/bin/env bash
#
# Nightly backup on the VPS (install with: echo '30 1 * * * root /srv/spu/src/deploy/docker/scripts/backup.sh >> /srv/spu/backups/backup.log 2>&1' > /etc/cron.d/spu-backup)
#
#   - every database the stack holds, dumped consistently and checked for a
#     completion trailer, kept 14 days
#   - storage/ and the legacy apps' files as a daily hard-linked snapshot
#     (unchanged files cost no space), kept 14 days
#
#     backup.sh          both
#     backup.sh db       databases only (deploy.sh runs this before every release)
#
# These stay on the same disk. Copy /srv/spu/backups off the server as well.
set -euo pipefail
ROOT="${SPU_ROOT:-/srv/spu}"
SPU="${ROOT}/src/deploy/docker/bin/spu"
# shellcheck disable=SC1091
set -a; source "${ROOT}/stack.env"; set +a
stamp="$(date -u +%Y%m%d-%H%M%S)"
mkdir -p "${ROOT}/backups/db" "${ROOT}/backups/files"
chmod 700 "${ROOT}/backups"

dump_dbs() {
    local dbs db file
    dbs="$("${SPU}" exec -T -e MYSQL_PWD="${DB_ROOT_PASSWORD}" db mariadb -uroot -N -e \
        "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT IN ('mysql','information_schema','performance_schema','sys')")"
    for db in ${dbs}; do
        file="${ROOT}/backups/db/${db}-${stamp}.sql.gz"
        "${SPU}" exec -T -e MYSQL_PWD="${DB_ROOT_PASSWORD}" db mariadb-dump -uroot \
            --single-transaction --quick --routines --triggers --events \
            --default-character-set=utf8mb4 "${db}" | gzip -6 > "${file}"
        gzip -cd "${file}" | tail -c 2000 | grep -q 'Dump completed' || { echo "✗ ${file} is truncated" >&2; exit 1; }
        chmod 600 "${file}"
        echo "✓ ${file} ($(du -h "${file}" | cut -f1))"
    done
    find "${ROOT}/backups/db" -name '*.sql.gz' -mtime +14 -delete
}

snapshot_files() {
    local dest="${ROOT}/backups/files/${stamp}" last
    last="$(ls -1d "${ROOT}/backups/files/"*/ 2>/dev/null | tail -1 || true)"
    mkdir -p "${dest}"
    for d in storage legacy-apps web; do
        [[ -d "${ROOT}/data/${d}" ]] || continue
        rsync -a --delete ${last:+--link-dest="${last}${d}"} \
            --exclude 'framework/cache/' --exclude 'framework/views/' \
            "${ROOT}/data/${d}/" "${dest}/${d}/"
    done
    echo "✓ files snapshot ${dest}"
    find "${ROOT}/backups/files" -mindepth 1 -maxdepth 1 -type d -mtime +14 -exec rm -rf {} +
}

case "${1:-all}" in
    db)  dump_dbs ;;
    all) dump_dbs; snapshot_files ;;
    *)   echo "usage: backup.sh [db|all]" >&2; exit 2 ;;
esac

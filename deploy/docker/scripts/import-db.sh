#!/usr/bin/env bash
#
# Load a cPanel database dump into the stack's MariaDB, replacing that database.
#
#     import-db.sh <dump.sql[.gz]> <database> [app user]
#
#     import-db.sh spuedu_v2.sql.gz spuedu_v2                  # the site (user from stack.env)
#     import-db.sh spuedu_spuh.sql.gz spuedu_spuh spuedu_spuh  # a legacy app; asks for the
#                                                              # password its config file uses
#
# DEFINER clauses are stripped (the cPanel users do not exist here), and the
# database is dropped and created fresh so no table survives from an earlier try.
set -euo pipefail
ROOT="${SPU_ROOT:-/srv/spu}"
dump="$1"; db="$2"; user="${3:-}"
SPU="${ROOT}/src/deploy/docker/bin/spu"
# shellcheck disable=SC1091
set -a; source "${ROOT}/stack.env"; set +a
[[ -f "${dump}" ]] || { echo "No such file: ${dump}" >&2; exit 1; }
[[ "${db}" =~ ^[A-Za-z0-9_]+$ ]] || { echo "Bad database name" >&2; exit 1; }

sql() { "${SPU}" exec -T -e MYSQL_PWD="${DB_ROOT_PASSWORD}" db mariadb -uroot "$@"; }

if [[ -z "${user}" && "${db}" == "${DB_DATABASE}" ]]; then
    user="${DB_USERNAME}"; grant_hosts=("%")
elif [[ -n "${user}" ]]; then
    read -r -s -p "Password for ${user} (from that app's config file): " user_pw; echo
    # The legacy apps connect to "localhost" over the shared socket.
    sql -e "CREATE USER IF NOT EXISTS '${user}'@'localhost' IDENTIFIED BY '${user_pw//\'/\'\'}';
            ALTER USER '${user}'@'localhost' IDENTIFIED BY '${user_pw//\'/\'\'}';"
    grant_hosts=("localhost")
fi

echo "Recreating ${db}"
sql -e "DROP DATABASE IF EXISTS \`${db}\`; CREATE DATABASE \`${db}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
for h in "${grant_hosts[@]:-}"; do
    [[ -n "${user}" && -n "${h}" ]] && sql -e "GRANT ALL PRIVILEGES ON \`${db}\`.* TO '${user}'@'${h}'; FLUSH PRIVILEGES;"
done

echo "Importing ${dump}"
start=$(date +%s)
{ if [[ "${dump}" == *.gz ]]; then gzip -cd "${dump}"; else cat "${dump}"; fi; } \
    | sed -E 's/DEFINER=`[^`]+`@`[^`]+`//g; s/DEFINER=[^ ]+@[^ ]+ //g' \
    | sql --max-allowed-packet=256M "${db}"
echo "Imported in $(( $(date +%s) - start ))s. Tables:"
sql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${db}';"

#!/usr/bin/env bash
#
# One-time preparation of the VPS (Ubuntu with Docker already installed).
# Run as root once the repository is at /srv/spu/src:
#
#     bash /srv/spu/src/deploy/docker/scripts/bootstrap-vps.sh
#
# Creates the /srv/spu layout, installs the few host tools the scripts use,
# opens only SSH, HTTP and HTTPS, and writes stack.env from the example with
# fresh database passwords. Re-running changes nothing that already exists.
set -euo pipefail
ROOT="${SPU_ROOT:-/srv/spu}"
SRC="${ROOT}/src/deploy/docker"

docker compose version >/dev/null || { echo "docker compose plugin missing" >&2; exit 1; }
apt-get update -qq
apt-get install -y -qq lftp rsync gzip curl python3 ufw >/dev/null

mkdir -p "${ROOT}"/{data/{storage,web,legacy,legacy-apps,mysql,caddy,certs},backups,secrets,dumps}
chmod 700 "${ROOT}/secrets" "${ROOT}/backups" "${ROOT}/dumps" "${ROOT}/data/certs"
chown -R 33:33 "${ROOT}/data/storage" "${ROOT}/data/web" "${ROOT}/data/legacy-apps"

if [[ ! -f "${ROOT}/stack.env" ]]; then
    umask 077
    sed -e "s|^DB_PASSWORD=$|DB_PASSWORD=$(openssl rand -hex 24)|" \
        -e "s|^DB_ROOT_PASSWORD=$|DB_ROOT_PASSWORD=$(openssl rand -hex 24)|" \
        "${SRC}/env/stack.env.example" > "${ROOT}/stack.env"
    echo "Wrote ${ROOT}/stack.env with new database passwords."
fi
ln -sfn "${SRC}/bin/spu" /usr/local/bin/spu

# Docker publishes 80/443 itself; ufw guards everything else (SSH stays open).
# Allow whatever port sshd really listens on, so enabling ufw cannot lock us out.
ssh_ports="$(sshd -T 2>/dev/null | awk '$1=="port"{print $2}')"
for p in ${ssh_ports:-22}; do ufw allow "${p}/tcp" >/dev/null; done
ufw allow 80/tcp >/dev/null
ufw allow 443/tcp >/dev/null
ufw --force enable >/dev/null

echo "✓ ${ROOT} is ready. Next: secrets/ftp.env, then pull-from-cpanel.sh media (see deploy/docker/README.md)."

#!/usr/bin/env bash
#
# Release a commit of dev on the VPS (the Docker counterpart of cPanel's
# "Update from Remote" + "Deploy HEAD Commit").
#
#     deploy.sh            latest origin/dev
#     deploy.sh <sha>      a specific commit
#
# Order: build the new image while the old one serves, dump the databases,
# run the release job (maintenance window, migrations, index, sitemap, checks),
# then recreate the services on the new image. A failed step stops here with
# the site still on the previous image and the dump beside it.
set -euo pipefail
ROOT="${SPU_ROOT:-/srv/spu}"
SPU="${ROOT}/src/deploy/docker/bin/spu"
ref="${1:-origin/dev}"

cd "${ROOT}/src"
git fetch --quiet origin dev
git checkout --quiet --detach "${ref}"
sha="$(git rev-parse --short HEAD)"
echo "▸ Deploying ${sha}: $(git log -1 --format=%s)"

"${SPU}" build web
docker tag spu-web:current "spu-web:${sha}"
"${ROOT}/src/deploy/docker/scripts/backup.sh" db
"${SPU}" run --rm release
"${SPU}" up -d --remove-orphans
echo "${sha} $(date -u +%FT%TZ)" >> "${ROOT}/deployed.log"
echo "✓ ${sha} is live. Previous images: docker images spu-web"

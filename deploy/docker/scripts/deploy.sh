#!/usr/bin/env bash
#
# Release a commit of dev on the VPS (the Docker counterpart of cPanel's
# "Update from Remote" + "Deploy HEAD Commit").
#
#     deploy.sh                          the latest origin/dev
#     deploy.sh <sha>                    a given commit (it must be on dev)
#     deploy.sh --approve <migration>    also approve a pending data migration
#                                        (repeat for each; the release refuses
#                                        unapproved row-writing migrations)
#     deploy.sh --rollback               go back to the release before this one
#
# Usually started from home by the "Deploy to the VPS" button on GitHub
# (deploy-watch.py picks the request up); on the server: sudo spu-deploy ...
#
# Order: build the new image while the old one serves, dump the databases,
# run the release job (maintenance window, migrations, index, sitemap, checks)
# on the new image, then recreate the services on it and check that the site
# answers. If anything fails the site stays on, or goes back to, the previous
# image and commit. The database is never restored automatically: the dump
# taken before the release is in /srv/spu/backups/db.
#
# One deploy at a time (/srv/spu/deploy.lock, shared with migrate.sh).
# Exit codes: 0 live, 2 blocked (data migrations need approval; nothing
# changed), 3 failed and the previous release is serving, 4 failed and the
# site does not answer, 5 another deploy or migrate.sh is running.
#
# Lines starting with "::" are read by deploy-watch.py for the status page.

set -uo pipefail
ROOT="${SPU_ROOT:-/srv/spu}"
SRC="${ROOT}/src"
SPU="${SRC}/deploy/docker/bin/spu"
RELEASES="${ROOT}/releases"          # one line per live release: "<sha> <time> <how>"
KEEP_IMAGES=5
APPROVAL_DIR="${ROOT}/data/storage/app/private/legacy-imports"

step()   { printf '\n▸ %s\n::step::%s\n' "$2" "$1"; }
result() { printf '::result::%s %s\n' "$1" "$2"; }
die()    { printf '✗ %s\n' "$2" >&2; result "$1" "$2"; exit "$3"; }

host() { sed -n 's/^SPU_CANONICAL_HOST=//p' "${ROOT}/stack.env" | tail -1 | tr -d "\"'" ; }

healthy() {  # healthy <seconds>: web container healthy and the home pages answer through Caddy
    local limit="$1" t=0 id state h code_ar code_en
    h="$(host)"; h="${h:-spu.edu.sy}"
    while (( t <= limit )); do
        id="$("${SPU}" ps -q web 2>/dev/null | head -1)"
        state="$([[ -n "${id}" ]] && docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "${id}" 2>/dev/null)"
        if [[ "${state}" == healthy ]]; then
            code_ar="$(curl -sk --noproxy "*" -o /dev/null -m 30 --resolve "${h}:443:127.0.0.1" -w '%{http_code}' "https://${h}/ar")"
            code_en="$(curl -sk --noproxy "*" -o /dev/null -m 30 --resolve "${h}:443:127.0.0.1" -w '%{http_code}' "https://${h}/en")"
            [[ "${code_ar}" == 200 && "${code_en}" == 200 ]] && return 0
        fi
        sleep 5; t=$((t + 5))
    done
    printf '   web: %s, /ar: %s, /en: %s\n' "${state:-not running}" "${code_ar:-no answer}" "${code_en:-no answer}" >&2
    return 1
}

previous_sha="" previous_image=""

back_to_previous() {  # put the previous image and commit back in place
    step rollback "Going back to ${previous_sha:-the previous release}"
    [[ -n "${previous_sha}" ]] && git -C "${SRC}" checkout -q --force --detach "${previous_sha}"
    [[ -n "${previous_image}" ]] && docker tag "${previous_image}" spu-web:current
    "${SPU}" up -d --remove-orphans >/dev/null 2>&1
    healthy 300
}

prune_images() {  # keep the newest few spu-web:<sha> images, and every one named in releases
    local keep tag
    keep="$(tail -n "${KEEP_IMAGES}" "${RELEASES}" 2>/dev/null | cut -d' ' -f1)"
    docker images spu-web --format '{{.Tag}} {{.CreatedAt}}' | sort -k2 -r | awk '{print $1}' \
        | grep -Ev '^(current|previous|<none>)$' | tail -n +$((KEEP_IMAGES + 1)) | while read -r tag; do
            grep -qx "${tag}" <<< "${keep}" || docker rmi "spu-web:${tag}" >/dev/null 2>&1 || true
        done
}

rollback_release() {
    exec 9>"${ROOT}/deploy.lock"
    flock -n 9 || die busy "Another deploy (or migrate.sh) is running. Try again when it has finished." 5
    local current target
    current="$(git -C "${SRC}" rev-parse --short HEAD)"
    target="$(awk -v c="${current}" '$1 != c {t = $1} END {print t}' "${RELEASES}" 2>/dev/null)"
    [[ -n "${target}" ]] || die failed "There is no earlier release on this server to go back to." 3
    docker image inspect "spu-web:${target}" >/dev/null 2>&1 || die failed "The image of ${target} is no longer on this server." 3
    previous_sha="$(git -C "${SRC}" rev-parse HEAD)"; previous_image="$(docker image inspect -f '{{.Id}}' spu-web:current)"
    step switch "Rolling back from ${current} to ${target} (the database stays as it is)"
    printf '::sha::%s\n' "${target}"
    git -C "${SRC}" checkout -q --force --detach "${target}" || die failed "Could not check out ${target}." 3
    docker tag "spu-web:${target}" spu-web:current
    "${SPU}" up -d --remove-orphans
    step health "Checking that the site answers"
    if healthy 300; then
        echo "${target} $(date -u +%FT%TZ) rollback" >> "${RELEASES}"
        echo "${target} $(date -u +%FT%TZ) rollback" >> "${ROOT}/deployed.log"
        result success "Rolled back to ${target}. The database was not changed."
        exit 0
    fi
    back_to_previous && die failed "${target} did not answer, so ${current} is serving again." 3
    die down "Neither ${target} nor ${current} answers. Look at: spu logs --tail 200 web" 4
}

deploy() {
    local ref="origin/dev" approvals=()
    while (( $# )); do
        case "$1" in
            --approve) [[ "${2:-}" =~ ^[0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6}_[A-Za-z0-9_]+$ ]] \
                           || die failed "Not a migration name: ${2:-}" 3
                       approvals+=("$2"); shift 2 ;;
            --rollback) rollback_release ;;
            -h|--help) sed -n '3,26p' "$0"; exit 0 ;;
            -*) die failed "Unknown option $1" 3 ;;
            *) ref="$1"; shift ;;
        esac
    done

    exec 9>"${ROOT}/deploy.lock"
    flock -n 9 || die busy "Another deploy (or migrate.sh) is running. Try again when it has finished." 5

    step fetch "Fetching dev from GitHub"
    git -C "${SRC}" fetch -q origin '+refs/heads/dev:refs/remotes/origin/dev' || die failed "Could not reach GitHub." 3
    local sha full
    full="$(git -C "${SRC}" rev-parse -q --verify "${ref}^{commit}")" || die failed "Unknown commit ${ref}." 3
    git -C "${SRC}" merge-base --is-ancestor "${full}" origin/dev || die failed "${ref} is not on dev; only dev is deployed." 3
    sha="$(git -C "${SRC}" rev-parse --short "${full}")"
    printf '::sha::%s\n' "${sha}"
    previous_sha="$(git -C "${SRC}" rev-parse HEAD)"
    previous_image="$(docker image inspect -f '{{.Id}}' spu-web:current 2>/dev/null)"
    if [[ -n "${previous_image}" ]]; then
        docker tag spu-web:current spu-web:previous
        # The release migrate.sh started has no line yet: record it, so --rollback can return to it.
        local prev_short; prev_short="$(git -C "${SRC}" rev-parse --short HEAD)"
        docker image inspect "spu-web:${prev_short}" >/dev/null 2>&1 || docker tag spu-web:current "spu-web:${prev_short}"
        [[ -s "${RELEASES}" ]] || echo "${prev_short} $(date -u +%FT%TZ) before-first-deploy" >> "${RELEASES}"
    fi
    echo "   ${sha}: $(git -C "${SRC}" log -1 --format=%s "${full}")"
    echo "   now live: $(git -C "${SRC}" rev-parse --short HEAD)"

    git -C "${SRC}" checkout -q --force --detach "${full}" || die failed "Could not check out ${sha}." 3

    step build "Building the image of ${sha} (the site keeps serving the current one)"
    if [[ "${SPU_DEPLOY_SKIP_BUILD:-}" == 1 ]] && docker image inspect "spu-web:${sha}" >/dev/null 2>&1; then
        echo "   using the image already built for ${sha}"
    elif ! SPU_IMAGE_TAG="${sha}" "${SPU}" build; then
        git -C "${SRC}" checkout -q --force --detach "${previous_sha}"
        die failed "The build of ${sha} failed (often the network). Nothing changed; ${previous_sha:0:8} keeps serving." 3
    fi

    if (( ${#approvals[@]} )); then
        mkdir -p "${APPROVAL_DIR}"
        printf 'migration=%s\n' "${approvals[@]}" > "${APPROVAL_DIR}/data-migrations.approved"
        chown 33:33 "${APPROVAL_DIR}" "${APPROVAL_DIR}/data-migrations.approved" 2>/dev/null || true
        echo "   approved data migrations: ${approvals[*]}"
    fi

    step backup "Saving every database before the release"
    if ! "${SRC}/deploy/docker/scripts/backup.sh" db; then
        git -C "${SRC}" checkout -q --force --detach "${previous_sha}"
        rm -f "${APPROVAL_DIR}/data-migrations.approved"
        die failed "The database backup failed, so nothing was released." 3
    fi

    step release "Release job on ${sha}: migrations, redirects, search index, sitemap, launch checks"
    local out; out="$(mktemp)"
    SPU_IMAGE_TAG="${sha}" "${SPU}" run --rm release 2>&1 | tee "${out}"
    local rc=${PIPESTATUS[0]}
    if (( rc != 0 )); then
        git -C "${SRC}" checkout -q --force --detach "${previous_sha}"
        rm -f "${APPROVAL_DIR}/data-migrations.approved"
        if grep -q 'Data migrations need approval' "${out}"; then
            sed -n '/Pending migrations that write rows/,/Data migrations need approval/p' "${out}" \
                | grep -Eo '[0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6}_[A-Za-z0-9_]+' | sort -u | sed 's/^/::pending::/'
            rm -f "${out}"
            die blocked "Data migrations need approval before ${sha} can go live. Nothing changed." 2
        fi
        rm -f "${out}"
        "${SPU}" up -d >/dev/null 2>&1
        if healthy 120; then
            die failed "The release job of ${sha} failed (read the lines above). ${previous_sha:0:8} keeps serving. If it had migrated, the dump is in ${ROOT}/backups/db." 3
        fi
        die down "The release job of ${sha} failed and the site does not answer. Look at: spu logs --tail 200 web" 4
    fi
    rm -f "${out}"

    step switch "Switching the site to ${sha}"
    docker tag "spu-web:${sha}" spu-web:current
    "${SPU}" up -d --remove-orphans

    step health "Checking that the site answers"
    if ! healthy 300; then
        back_to_previous && die failed "${sha} did not answer after the switch, so ${previous_sha:0:8} is serving again. Its database dump is in ${ROOT}/backups/db." 3
        die down "${sha} did not answer and going back failed too. Look at: spu logs --tail 200 web" 4
    fi

    echo "${sha} $(date -u +%FT%TZ) deploy" >> "${RELEASES}"
    echo "${sha} $(date -u +%FT%TZ) deploy" >> "${ROOT}/deployed.log"
    install -m 755 "${SRC}/deploy/docker/scripts/spu-deploy" /usr/local/sbin/spu-deploy 2>/dev/null || true
    prune_images
    result success "${sha} is live."
    exit 0
}

# Everything above is only definitions: bash has read the whole file before the
# checkout below replaces it on disk.
deploy "$@"

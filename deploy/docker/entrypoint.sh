#!/usr/bin/env bash
#
# One image, several jobs. The first argument picks the job:
#
#   web        Apache + PHP, the public site and the admin panel   (default)
#   queue      the database queue worker (form mail, event registrations)
#   scheduler  Laravel's scheduler (site stats, scheduled publishing, sitemap)
#   release    one-off: migrations and the post-deploy steps (release.sh)
#   artisan …  one-off: php artisan … as www-data
#
# Every job first makes sure storage/ has the directories Laravel expects and
# builds this container's own framework caches (bootstrap/cache is per
# container and never shipped).

set -euo pipefail

APP=/var/www/spu
WEB="${APP}/public"
DIST=/opt/spu-public
role="${1:-web}"
[[ $# -gt 0 ]] && shift

as_www() { setpriv --reuid=www-data --regid=www-data --init-groups "$@"; }
log() { printf '[spu-%s] %s\n' "${role}" "$*" >&2; }

[[ -f "${APP}/.env" ]] || { log "No ${APP}/.env mounted. Build /srv/spu/app.env with scripts/make-app-env.sh."; exit 1; }
setpriv --reuid=www-data --regid=www-data --init-groups test -r "${APP}/.env" \
    || { log "www-data cannot read .env: chown 33:33 /srv/spu/app.env && chmod 600 /srv/spu/app.env"; exit 1; }

prepare_storage() {
    # Created as www-data, so a directory made here is never root-owned (a
    # root-owned cache/data made every cache write fail silently).
    chown www-data:www-data "${APP}/storage" "${APP}/bootstrap/cache"
    as_www mkdir -p "${APP}/storage/app/public" "${APP}/storage/app/private" \
             "${APP}/storage/app/site-stats/queue" \
             "${APP}/storage/framework/cache/data" "${APP}/storage/framework/cache/webhook" \
             "${APP}/storage/framework/cache/rate-limiter" "${APP}/storage/framework/sessions" \
             "${APP}/storage/framework/views" "${APP}/storage/logs"
    # Anything a copy or an earlier root process left behind near the top.
    find "${APP}/storage" -maxdepth 4 ! -user www-data -exec chown www-data:www-data {} + 2>/dev/null || true
}

build_caches() {
    # Per container: config and routes come from this image and the mounted .env.
    rm -f "${APP}"/bootstrap/cache/{packages,services,config,routes-v7,events}.php
    as_www php "${APP}/artisan" package:discover --no-ansi >/dev/null
    as_www php "${APP}/artisan" optimize --no-ansi >/dev/null
}

publish_docroot() {
    # Like cpanel-deploy.sh: copy the release's public/ over the document root
    # without --delete. Vite hashes build filenames, and pages cached by the
    # previous release still name the previous build.
    [[ -f "${DIST}/build/manifest.json" ]] || { log "The image has no public/build/manifest.json"; exit 1; }
    rsync -a --chown=www-data:www-data --exclude='/_legacy' --exclude='/storage' \
        --exclude='/forms' --exclude='/Medicine' --exclude='/spuh' "${DIST}/" "${WEB}/"
    ln -sfn "${APP}/storage/app/public" "${WEB}/storage"

    # The cPanel staging robots.txt said Disallow: / to every crawler. Copied
    # over by mistake it would hide the live site; the application serves the
    # real robots.txt itself when no file is on disk.
    if [[ -f "${WEB}/robots.txt" ]] && grep -qi 'staging' "${WEB}/robots.txt"; then
        log "Removing the staging robots.txt overlay from the document root"
        rm -f "${WEB}/robots.txt"
    fi
    if grep -q 'STAGING ONLY' "${WEB}/.htaccess" 2>/dev/null; then
        log "The document root .htaccess still carries STAGING ONLY blocks"; exit 1
    fi
}

write_legacy_routing() {
    # Where the old site's media and its three live PHP applications are served
    # from. local = this server (copied to /srv/spu/data); remote = proxied to
    # the old host, a bridge for while a copy is still running; off = not served.
    local out=/etc/apache2/spu-generated
    mkdir -p "${out}"
    rm -f "${out}"/*.conf
    local origin="${SPU_LEGACY_ORIGIN%/}"

    case "${SPU_LEGACY_APPS}" in
        local)
            cat > "${out}/legacy-apps.conf" <<'CONF'
# /forms, /Medicine and /spuh run in the legacy-apps container (PHP 8.3).
ProxyPreserveHost On
ProxyPassMatch "^/(forms|Medicine|spuh)(/.*)?$" "http://legacy-apps:80/$1$2"
ProxyPassReverse "/" "http://legacy-apps:80/"
CONF
            ;;
        remote)
            cat > "${out}/legacy-apps.conf" <<CONF
# /forms, /Medicine and /spuh still run on the old host.
SSLProxyEngine On
SSLProxyCheckPeerName off
SSLProxyCheckPeerCN off
SSLProxyVerify none
ProxyPreserveHost On
ProxyPassMatch "^/(forms|Medicine|spuh)(/.*)?\$" "${origin}/\$1\$2"
CONF
            ;;
        off) ;;
        *) log "SPU_LEGACY_APPS must be local, remote or off (got ${SPU_LEGACY_APPS})"; exit 1 ;;
    esac

    if [[ "${SPU_LEGACY_MEDIA}" == "remote" ]]; then
        # public/.htaccess rewrites /images/x to /_legacy/images/x internally;
        # the internal redirect then lands here and is fetched from the old
        # host at the file's original address.
        cat > "${out}/legacy-media.conf" <<CONF
SSLProxyEngine On
SSLProxyCheckPeerName off
SSLProxyCheckPeerCN off
SSLProxyVerify none
ProxyPreserveHost On
ProxyPassMatch "^/_legacy/downloads/root/(.*)\$" "${origin}/downloads/\$1"
ProxyPassMatch "^/_legacy/(.*)\$" "${origin}/\$1"
CONF
    elif [[ "${SPU_LEGACY_MEDIA}" != "local" ]]; then
        log "SPU_LEGACY_MEDIA must be local or remote (got ${SPU_LEGACY_MEDIA})"; exit 1
    fi
}

case "${role}" in
    web)
        prepare_storage
        publish_docroot
        write_legacy_routing
        build_caches
        log "Apache up (legacy apps: ${SPU_LEGACY_APPS}, legacy media: ${SPU_LEGACY_MEDIA})"
        exec apache2-foreground "$@"
        ;;
    queue)
        prepare_storage
        build_caches
        # The cPanel cron ran a worker per minute (--max-time=50). A long-lived
        # worker restarts itself every hour and after every release
        # (queue:restart), and Docker restarts it when it exits.
        exec setpriv --reuid=www-data --regid=www-data --init-groups \
            php "${APP}/artisan" queue:work database --queue=default \
            --sleep=3 --tries=3 --timeout=40 --max-time=3600 --memory=256 "$@"
        ;;
    scheduler)
        prepare_storage
        build_caches
        exec setpriv --reuid=www-data --regid=www-data --init-groups \
            php "${APP}/artisan" schedule:work "$@"
        ;;
    release)
        prepare_storage
        exec spu-release "$@"
        ;;
    artisan)
        exec setpriv --reuid=www-data --regid=www-data --init-groups php "${APP}/artisan" "$@"
        ;;
    *)
        exec "${role}" "$@"
        ;;
esac

# syntax=docker/dockerfile:1.7
#
# The SPU website (v2) as one image, used by four services in compose.yaml:
# web (Apache + PHP), queue, scheduler and the one-off release job.
#
# Build from the repository root:
#     docker build -f deploy/docker/Dockerfile -t spu-web .
#
# The image holds the code and nothing else. The database, storage/, the
# document root's runtime files (sitemaps, image derivatives) and the old site's
# media live on the host under /srv/spu/data and are mounted in.

ARG PHP_VERSION=8.4

# ── PHP dependencies ─────────────────────────────────────────────────────────
FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Scripts are skipped here: package:discover needs the application, and the
# manifest is rebuilt at container start anyway (bootstrap/cache is never shipped).
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
        --no-interaction --no-progress --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# ── Runtime ──────────────────────────────────────────────────────────────────
FROM php:${PHP_VERSION}-apache-bookworm AS app

COPY --from=mlocati/php-extension-installer:2.7 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath exif gd intl mysqli pdo_mysql zip opcache pcntl \
 && apt-get update \
 && apt-get install -y --no-install-recommends rsync gzip curl mariadb-client \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite headers expires deflate mime remoteip proxy proxy_http ssl setenvif \
 && a2dissite 000-default \
 && sed -ri 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf

COPY deploy/docker/php/spu.ini /usr/local/etc/php/conf.d/zz-spu.ini
COPY deploy/docker/apache/spu.conf /etc/apache2/sites-available/spu.conf
RUN a2ensite spu

WORKDIR /var/www/spu
COPY --from=vendor --chown=www-data:www-data /app /var/www/spu

# public/ in the image is the release's copy of the document root. The live
# document root is a host directory mounted over /var/www/spu/public (it also
# holds files the application writes at runtime), so the entrypoint copies this
# into it on every start, the way cpanel-deploy.sh publishes into the cPanel
# document root.
RUN rm -rf bootstrap/cache/*.php storage .env \
 && mv public /opt/spu-public \
 && mkdir -p public storage bootstrap/cache \
 && chown -R www-data:www-data public storage bootstrap/cache \
 && rm -rf node_modules tests

COPY deploy/docker/entrypoint.sh /usr/local/bin/spu-entrypoint
COPY deploy/docker/release.sh /usr/local/bin/spu-release
RUN chmod 0755 /usr/local/bin/spu-entrypoint /usr/local/bin/spu-release

ENV APACHE_MAX_WORKERS=40 \
    SPU_PROXY_SUBNET=172.30.0.0/24 \
    SPU_LEGACY_APPS=local \
    SPU_LEGACY_MEDIA=local \
    SPU_LEGACY_ORIGIN=https://184.107.185.129

ENTRYPOINT ["spu-entrypoint"]
CMD ["web"]

# The three PHP applications that lived in the old site's document root and are
# still in use: /forms (surveys, complaints), /Medicine (Faculty of Medicine
# recruitment) and /spuh (University Hospital job applications). On cPanel the
# main domain ran them on ea-php83, so this container does too.
#
# Their files are mounted from /srv/spu/data/legacy-apps. Their databases are
# in the same MariaDB as the site; they connect to "localhost", which PHP turns
# into the MariaDB socket shared at /run/mysqld, so their config files need no
# edit.
FROM php:8.3-apache-bookworm
COPY --from=mlocati/php-extension-installer:2.7 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions mysqli pdo_mysql gd zip mbstring intl exif opcache \
 && a2enmod rewrite headers remoteip setenvif \
 && sed -ri 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf
COPY legacy-apps/php.ini /usr/local/etc/php/conf.d/zz-legacy.ini
COPY legacy-apps/apache.conf /etc/apache2/sites-available/000-default.conf

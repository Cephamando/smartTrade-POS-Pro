# syntax=docker/dockerfile:1
# Sealed client runtime. Build from the repository root:
#   docker build -f client/Dockerfile -t smarttradepos-pro-client:sealed .
#
# PHP source is compiled in the builder stage and is not copied into the
# final image. The runtime receives zero-byte paths plus the OPcache file cache.

ARG PHP_IMAGE=php:8.3.36-apache

FROM ${PHP_IMAGE} AS php-base

ARG DEBIAN_FRONTEND=noninteractive
ENV TZ=Africa/Lusaka \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update && apt-get install -y \
        libzip-dev zip unzip curl \
        libpng-dev libjpeg-dev libfreetype6-dev \
        libonig-dev libxml2-dev \
        tzdata \
        --no-install-recommends \
    && ln -snf /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo "$TZ" > /etc/timezone \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        mysqli pdo pdo_mysql zip gd mbstring exif pcntl bcmath \
    && docker-php-ext-enable opcache

RUN a2enmod rewrite headers \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf 'ServerTokens Prod\nServerSignature Off\n' >> /etc/apache2/apache2.conf

COPY client/php/app.ini /usr/local/etc/php/conf.d/app.ini
COPY client/apache/uploads-deny.conf /etc/apache2/conf-available/uploads-deny.conf
RUN a2enconf uploads-deny

WORKDIR /var/www/html

FROM php-base AS builder

COPY app /var/www/html/app
COPY config /var/www/html/config
COPY public /var/www/html/public
COPY .htaccess /var/www/html/.htaccess
COPY user_guide.md TRAINING_MANUAL.md /var/www/html/
COPY client/docker/strip-application.sh /tmp/strip-application.sh
COPY client/docker/compile-opcache.php /tmp/compile-opcache.php

RUN chmod 0755 /tmp/strip-application.sh \
    && /tmp/strip-application.sh /var/www/html \
    && rm -f /tmp/strip-application.sh \
    && mkdir -p /var/www/opcache /var/www/html/public/uploads/promo \
    && chown -R www-data:www-data /var/www/html /var/www/opcache \
    && su -s /bin/sh www-data -c 'php /tmp/compile-opcache.php /var/www/html /var/www/opcache' \
    && rm -f /tmp/compile-opcache.php \
    && find /var/www/opcache -type d -exec chmod 0750 '{}' + \
    && find /var/www/opcache -type f -exec chmod 0400 '{}' + \
    && chown -R www-data:www-data /var/www/opcache /var/www/html \
    && find /var/www/html -type d -exec chmod 0555 '{}' + \
    && find /var/www/html -type f -exec chmod 0444 '{}' + \
    && chmod 0775 /var/www/html/public/uploads /var/www/html/public/uploads/promo

FROM php-base AS runtime

COPY client/php/opcache-sealed.ini /usr/local/etc/php/conf.d/zz-opcache-sealed.ini
COPY client/docker/entrypoint.sh /usr/local/bin/client-entrypoint.sh
COPY client/docker/verify-sealed.php /tmp/verify-sealed.php
COPY --from=builder --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=builder --chown=www-data:www-data /var/www/opcache /var/www/opcache

RUN chmod 0755 /usr/local/bin/client-entrypoint.sh \
    && find /var/www/opcache -type d -exec chmod 0750 '{}' + \
    && find /var/www/opcache -type f -exec chmod 0400 '{}' + \
    && chown -R www-data:www-data /var/www/opcache /var/www/html \
    && find /var/www/html -type d -exec chmod 0555 '{}' + \
    && find /var/www/html -type f -exec chmod 0444 '{}' + \
    && chmod 0775 /var/www/html/public/uploads /var/www/html/public/uploads/promo \
    && apachectl configtest \
    && apachectl -M | grep -q rewrite_module \
    && apachectl -M | grep -q headers_module \
    && test "$(printenv APACHE_DOCUMENT_ROOT)" = "/var/www/html/public" \
    && grep -q 'DocumentRoot \${APACHE_DOCUMENT_ROOT}' /etc/apache2/sites-available/000-default.conf \
    && php /tmp/verify-sealed.php \
    && su -s /bin/sh www-data -c 'php /tmp/verify-sealed.php' \
    && find /var/www/opcache -type f -name 'verify-sealed.php.bin' -delete \
    && find /var/www/opcache -depth -type d -empty -delete \
    && rm -f /tmp/verify-sealed.php

WORKDIR /var/www/html
EXPOSE 80
CMD ["/usr/local/bin/client-entrypoint.sh"]

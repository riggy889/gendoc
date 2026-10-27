# syntax=docker/dockerfile:1

# Production images for GenDoc (development uses docker/php/Dockerfile and compose.yaml).
#
# Two final targets share the build stages below:
#   app  PHP-FPM with the code baked in            docker build --target app .
#   web  nginx serving public/ and proxying to app  docker build --target web .

# --- PHP runtime: the extensions the app needs, with no compilers left behind ---
FROM php:8.4.27-fpm-alpine3.24 AS php

RUN apk add --no-cache icu-libs fcgi \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev \
    && docker-php-ext-install intl opcache \
    && apk del .build-deps

# --- Composer dependencies (no dev packages) and an optimised autoloader ---
FROM php AS vendor

RUN apk add --no-cache unzip
COPY --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction

# --- Front-end build. Needs vendor/ for Livewire's JavaScript, which app.js imports ---
FROM node:24.22.1-bookworm-slim AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
COPY --from=vendor /app/vendor/livewire/livewire/dist vendor/livewire/livewire/dist
RUN npm run build && find public/build -name '*.map' -delete

# --- app: PHP-FPM, running as the unprivileged www-data user ---
FROM php AS app

COPY docker/prod/php.ini /usr/local/etc/php/conf.d/zz-gendoc.ini
COPY docker/prod/fpm.conf /usr/local/etc/php-fpm.d/zz-gendoc.conf
COPY docker/prod/entrypoint.sh /usr/local/bin/gendoc-entrypoint

WORKDIR /var/www/html

# Code is owned by root and read-only to the app; only storage/ and bootstrap/cache/ are writable.
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build public/build
# The PDF renderer embeds the @fontsource font files (see App\Pdf\EmbeddedFonts). Only fonts, no Node.
COPY --from=assets /app/node_modules/@fontsource node_modules/@fontsource
COPY --from=assets /app/node_modules/@fontsource-variable node_modules/@fontsource-variable

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/gendoc-entrypoint

USER www-data

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong

ENTRYPOINT ["gendoc-entrypoint"]
CMD ["php-fpm"]

# --- web: nginx serving the built assets, running unprivileged on port 8080 ---
FROM nginxinc/nginx-unprivileged:1.30.6-alpine AS web

COPY docker/prod/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=assets /app/public /var/www/html/public

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1:8080/up || exit 1

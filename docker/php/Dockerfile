# Development image for the PHP-FPM "app" service.
# The source code is bind-mounted, so nothing is copied in here.
FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000
ARG INSTALL_XDEBUG=false

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev \
    && docker-php-ext-install intl zip opcache pcntl \
    && if [ "$INSTALL_XDEBUG" = "true" ]; then pecl install xdebug && docker-php-ext-enable xdebug; fi \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-gendoc.ini

# Run FPM and CLI as a user matching the host user, so bind-mounted files stay owned by you.
RUN groupadd --gid "$GID" app \
    && useradd --uid "$UID" --gid "$GID" --create-home --shell /bin/bash app \
    && sed -i 's/^user = .*/user = app/; s/^group = .*/group = app/' /usr/local/etc/php-fpm.d/www.conf

USER app
WORKDIR /var/www/html

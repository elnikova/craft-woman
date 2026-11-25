# PHP-FPM for local development. The code is mounted from the host; vendor lives in a named volume.
FROM php:8.3-fpm-bookworm

# Extensions Laravel and this project need (PostgreSQL, intl for Intl formatting, zip for Composer, gd for images)
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql intl zip gd bcmath pcntl opcache

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# The project is mounted from Windows with a different owner; let git (Composer, Pint --dirty) trust it
RUN git config --system --add safe.directory /var/www/html

COPY php.ini /usr/local/etc/php/conf.d/zz-craft-woman.ini

WORKDIR /var/www/html

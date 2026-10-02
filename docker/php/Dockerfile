FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends git libicu-dev unzip \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql \
    && a2enmod rewrite \
    && printf 'date.timezone=America/Guatemala\n' > /usr/local/etc/php/conf.d/timezone.ini \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

RUN mkdir -p var \
    && chown -R www-data:www-data var

ENV COMPOSER_ALLOW_SUPERUSER=1

FROM php:8.5-apache AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libicu-dev libsqlite3-dev unzip curl \
    && docker-php-ext-install pdo_sqlite zip intl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ---

FROM base AS builder

WORKDIR /var/www/html
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-scripts
COPY . .
RUN php bin/console asset-map:compile

# ---

FROM base AS tester

COPY docker/php.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html
COPY composer.json ./
RUN composer install --no-scripts
COPY . .
RUN APP_ENV=dev php bin/console cache:warmup
ENTRYPOINT []
CMD ["sh", "-c", "vendor/bin/phpcs && vendor/bin/phpstan analyse --no-progress --memory-limit=512M && vendor/bin/phpunit --testdox"]

# ---

FROM base AS production

LABEL maintainer="ojooss"

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/entrypoint.sh /usr/local/bin/custom-entrypoint
RUN chmod +x /usr/local/bin/custom-entrypoint

WORKDIR /var/www/html
COPY --from=builder /var/www/html/ /var/www/html/

ENTRYPOINT ["/usr/local/bin/custom-entrypoint"]
CMD ["apache2-foreground"]

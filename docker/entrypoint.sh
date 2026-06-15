#!/bin/bash
set -e

mkdir -p /var/www/html/var/data /var/www/html/var/log

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

php bin/console cache:warmup

chown -R www-data:www-data /var/www/html/var
chmod -R a+rw /var/www/html/var

exec /usr/local/bin/docker-php-entrypoint "$@"

#!/bin/sh
set -e

# Исправляем права на storage и bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Запускаем оригинальную точку входа PHP-FPM
exec docker-php-entrypoint "$@"

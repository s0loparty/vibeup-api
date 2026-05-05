#!/bin/sh
set -e

# Если контейнер запущен от root (обычно так и есть), меняем владельца и права
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
    # Переключаемся на www-data и запускаем оригинальный entrypoint PHP
    exec su-exec www-data docker-php-entrypoint "$@"
else
    # Если уже www-data – просто запускаем
    exec docker-php-entrypoint "$@"
fi

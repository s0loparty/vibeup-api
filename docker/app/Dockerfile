FROM php:8.4-fpm-alpine

# Установка системных пакетов и расширений PHP
RUN apk add --no-cache \
    bash git curl zip unzip su-exec \
    libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    libzip-dev oniguruma-dev icu-dev \
    postgresql-dev \
    autoconf build-base \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd pdo_pgsql mbstring zip exif pcntl bcmath intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf build-base

# Копируем конфиги PHP-FPM
COPY ./docker/app/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Устанавливаем Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Копируем composer файлы для кэширования зависимостей
COPY composer.json composer.lock ./

# Устанавливаем PHP-зависимости (без dev и без выполнения скриптов)
RUN composer install --no-interaction --optimize-autoloader --no-dev --no-scripts

# Копируем весь код приложения (включая уже собранный Vite frontend)
COPY . .

# Запускаем post-autoload-dump (для package:discover и т.п.)
RUN composer run-script post-autoload-dump

# Копируем entrypoint и даём права
COPY ./docker/app/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Создаём необходимые директории и выставляем права (от root)
RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Переключаемся на www-data
USER www-data

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

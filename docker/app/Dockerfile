FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        libpq-dev \
        oniguruma-dev \
        libzip-dev \
        zip \
        unzip \
        git \
        curl \
        nginx \
        nodejs \
        npm \
    && docker-php-ext-install pdo_pgsql mbstring zip exif \
    && apk add --no-cache --virtual .build-deps autoconf build-base \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Копируем composer.json, composer.lock и artisan до установки
COPY composer.json composer.lock ./
COPY artisan ./

# Устанавливаем зависимости (скриптам уже доступен artisan)
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Копируем package.json для frontend
COPY package.json package-lock.json ./
RUN npm ci && npm run build

# Копируем остальной код
COPY . .

# Права на storage и bootstrap/cache
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

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
    && docker-php-ext-install pdo_pgsql mbstring zip exif pcntl \
    && apk add --no-cache --virtual .build-deps autoconf build-base \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Копируем composer.json и composer.lock отдельно для кэширования
COPY composer.json composer.lock ./

# Устанавливаем зависимости БЕЗ выполнения скриптов (так как artisan и bootstrap/app.php ещё не скопированы)
RUN composer install --no-interaction --optimize-autoloader --no-dev --no-scripts

# Копируем package.json и устанавливаем фронтенд (если нужен)
COPY package.json package-lock.json ./
RUN npm ci && npm run build || true

# Копируем весь код приложения (включая artisan, bootstrap, config и т.д.)
COPY . .

# Теперь запускаем скрипты composer (post-autoload-dump, который вызывает package:discover и т.п.)
RUN composer run-script post-autoload-dump

# Права на storage и bootstrap/cache
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

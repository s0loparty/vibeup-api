FROM php:8.4-fpm-alpine

# Установка системных зависимостей
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
    npm

RUN docker-php-ext-install pdo_pgsql mbstring zip

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Копируем composer.json сначала для кэширования зависимостей
COPY composer.json composer.lock ./
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Копируем package.json для frontend
COPY package.json package-lock.json ./
RUN npm ci && npm run build

# Остальные файлы
COPY . .

# Права на storage и bootstrap/cache
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

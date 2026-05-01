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

COPY composer.json composer.lock ./
RUN composer install --no-interaction --optimize-autoloader --no-dev

COPY package.json package-lock.json ./
RUN npm ci && npm run build

COPY . .

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

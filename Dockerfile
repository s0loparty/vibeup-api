# syntax=docker/dockerfile:1

# =============================================================
# Stage 1: Dependencies
# =============================================================
FROM dunglas/frankenphp:1.2-php8.5 AS base

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    zip \
    intl \
    bcmath \
    gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# =============================================================
# Stage 2: Development dependencies (for npm build)
# =============================================================
FROM base AS development

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP dependencies
COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-dev --no-interaction --no-progress

# Install Node dependencies and build assets
COPY package.json package-lock.json ./
RUN npm ci --no-audit --progress=false
COPY . .
RUN npm run build

# =============================================================
# Stage 3: Production
# =============================================================
FROM base AS production

# Create system user for running the app
RUN groupadd -g 1000 -r www && useradd -u 1000 -r -g www www

# Copy composer dependencies from development stage
COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-dev --no-interaction --no-progress

# Copy application code
COPY . .

# Copy built assets from development stage
COPY --from=development /var/www/html/public/build ./public/build

# Set permissions
RUN chown -R www:www /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create directories for Laravel
RUN mkdir -p /var/www/html/storage/app/public \
    /var/www/html/storage/logs \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    && chown -R www:www /var/www/html/storage

# Expose port 80 (HTTP) and 443 (HTTPS)
EXPOSE 80 443

# Set environment variables
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -f http://localhost:2019/metrics || exit 1

# Set working directory
WORKDIR /var/www/html

# Run as non-root user
USER www

# Start FrankenPHP
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

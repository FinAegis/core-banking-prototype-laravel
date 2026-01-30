# ============================================================================
# FinAegis Core Banking Platform - Production Dockerfile
# Multi-stage build for minimal, secure production images
# ============================================================================

# ----------------------------------------------------------------------------
# Stage 1: Composer dependencies
# ----------------------------------------------------------------------------
FROM composer:2.8 AS composer

WORKDIR /app

# Copy composer files first for layer caching
COPY composer.json composer.lock ./

# Install dependencies without dev packages
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

# Copy application code
COPY . .

# Generate optimized autoloader (skip scripts to avoid artisan commands without APP_KEY)
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative --no-scripts

# ----------------------------------------------------------------------------
# Stage 2: Node.js - Build frontend assets
# ----------------------------------------------------------------------------
FROM node:22-alpine AS node

WORKDIR /app

# Install build dependencies for native modules (usb, node-hid for hardware wallets)
RUN apk add --no-cache \
    python3 \
    make \
    g++ \
    linux-headers \
    eudev-dev \
    libusb-dev

# Copy package files for layer caching
COPY package.json package-lock.json ./

# Install dependencies (USB/HID modules need native compilation)
RUN npm ci --prefer-offline

# Copy source files needed for build
COPY resources ./resources
COPY vite.config.js tailwind.config.js tailwind.safelist.js postcss.config.js ./
COPY public ./public

# Build production assets
RUN npm run build

# ----------------------------------------------------------------------------
# Stage 3: Production image
# ----------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS production

LABEL maintainer="FinAegis <dev@finaegis.org>"
LABEL org.opencontainers.image.title="FinAegis Core Banking Platform"
LABEL org.opencontainers.image.description="Enterprise-grade Core Banking Platform"
LABEL org.opencontainers.image.source="https://github.com/FinAegis/core-banking-prototype-laravel"

# Build arguments
ARG APP_ENV=production
ARG APP_DEBUG=false
ARG PHP_MEMORY_LIMIT=256M
ARG PHP_MAX_EXECUTION_TIME=60
ARG PHP_UPLOAD_MAX_FILESIZE=50M
ARG PHP_POST_MAX_SIZE=50M

# Environment variables
ENV APP_ENV=${APP_ENV} \
    APP_DEBUG=${APP_DEBUG} \
    PHP_MEMORY_LIMIT=${PHP_MEMORY_LIMIT} \
    PHP_MAX_EXECUTION_TIME=${PHP_MAX_EXECUTION_TIME} \
    PHP_UPLOAD_MAX_FILESIZE=${PHP_UPLOAD_MAX_FILESIZE} \
    PHP_POST_MAX_SIZE=${PHP_POST_MAX_SIZE} \
    COMPOSER_ALLOW_SUPERUSER=1

# Install system dependencies
RUN apk add --no-cache \
    # Required for intl
    icu-dev \
    icu-libs \
    # Required for GMP (crypto)
    gmp-dev \
    # Required for pcntl (Horizon)
    linux-headers \
    # Required for MySQL/MariaDB
    mariadb-client \
    # Required for Redis
    libzip-dev \
    # Nginx
    nginx \
    # Supervisor for process management
    supervisor \
    # Utilities
    curl \
    ca-certificates \
    tzdata \
    && rm -rf /var/cache/apk/*

# Install build dependencies for PECL extensions
RUN apk add --no-cache --virtual .build-deps \
    autoconf \
    g++ \
    make

# Install PHP extensions
RUN docker-php-ext-install \
    bcmath \
    gmp \
    intl \
    opcache \
    pcntl \
    pdo_mysql \
    zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Configure PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Configure nginx
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Configure supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set up application directory
WORKDIR /var/www/html

# Copy application from previous stages
COPY --from=composer /app/vendor ./vendor
COPY --from=node /app/public/build ./public/build
COPY . .

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create necessary directories
RUN mkdir -p \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/run/nginx \
    /var/run/php-fpm \
    && chown -R www-data:www-data /var/www/html/storage

# Note: Laravel config/route/view caching should be done at container startup
# via entrypoint script, as they require environment variables (APP_KEY, DB_*, etc.)
# that are not available during Docker build.

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/api/monitoring/alive || exit 1

# Expose ports
EXPOSE 80

# Start supervisor (manages nginx + php-fpm)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# ----------------------------------------------------------------------------
# Stage 4: Horizon worker image (extends production)
# ----------------------------------------------------------------------------
FROM production AS horizon

# Override command to run Horizon
CMD ["php", "/var/www/html/artisan", "horizon"]

# Different health check for Horizon
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD php /var/www/html/artisan horizon:status || exit 1

# ----------------------------------------------------------------------------
# Stage 5: Scheduler image (extends production)
# ----------------------------------------------------------------------------
FROM production AS scheduler

# Create scheduler script
RUN echo '#!/bin/sh' > /usr/local/bin/scheduler.sh \
    && echo 'while [ true ]; do' >> /usr/local/bin/scheduler.sh \
    && echo '  php /var/www/html/artisan schedule:run --verbose --no-interaction' >> /usr/local/bin/scheduler.sh \
    && echo '  sleep 60' >> /usr/local/bin/scheduler.sh \
    && echo 'done' >> /usr/local/bin/scheduler.sh \
    && chmod +x /usr/local/bin/scheduler.sh

# Override command to run scheduler
CMD ["/usr/local/bin/scheduler.sh"]

# Different health check for scheduler
HEALTHCHECK --interval=60s --timeout=10s --start-period=10s --retries=3 \
    CMD php /var/www/html/artisan tinker --execute="exit(0)" || exit 1

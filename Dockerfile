# =========================
# Stage 1: Composer install
# =========================
FROM composer:2 AS vendor

WORKDIR /app

# copy only composer files first (for cache)
COPY composer.json composer.lock ./

# install dependencies (NO artisan here yet)
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist


# =========================
# Stage 2: PHP Application
# =========================
FROM php:8.2-cli

WORKDIR /var/www/html

# =========================
# System dependencies
# =========================
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

# =========================
# PHP extensions
# =========================
RUN docker-php-ext-install \
    pdo \
    pdo_sqlite \
    mbstring \
    xml \
    curl

# =========================
# Copy application
# =========================
COPY --from=vendor /app /var/www/html
COPY . .

# =========================
# Laravel setup
# =========================
RUN cp .env.example .env || true \
    && touch database/database.sqlite \
    && mkdir -p storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && php artisan key:generate --force || true

# =========================
# Expose port
# =========================
EXPOSE 8000

# =========================
# Start server
# =========================
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
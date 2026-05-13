# =========================
# Stage 1: Composer install
# =========================
FROM composer:2 AS vendor

WORKDIR /app

# copy only composer files first (for cache)
COPY composer.json composer.lock ./

# install dependencies; --no-scripts skips post-autoload-dump (needs artisan), not copied yet
RUN composer install \
    --no-dev \
    --no-scripts \
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
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY . .

# =========================
# Laravel setup
# =========================
RUN cp .env.example .env || true \
    && touch database/database.sqlite \
    && mkdir -p storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && php artisan key:generate --force || true

# Default for local `docker run` when PORT is unset; Railway injects PORT at runtime.
ENV PORT=8000

# =========================
# Expose port (informational; PaaS uses $PORT)
# =========================
EXPOSE 8000

# =========================
# Start server (must listen on 0.0.0.0:$PORT for Railway healthchecks)
# =========================
CMD ["sh", "-c", "exec php artisan serve --host=0.0.0.0 --port=${PORT}"]
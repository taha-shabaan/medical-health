#!/bin/bash
set -e

echo "Starting Medical App Backend..."

# Wait for database to be ready
echo "Checking database..."
if [ ! -f database/database.sqlite ]; then
    echo "Creating SQLite database..."
    touch database/database.sqlite
fi

# Run migrations if needed
echo "Running migrations..."
php artisan migrate --force --no-interaction || true

# Seed database if empty
php artisan db:seed --force --no-interaction || true

# Clear and optimize caches
echo "Optimizing application..."
php artisan config:clear
php artisan route:clear

# Start the server
echo "Starting server on port 8000..."
exec php artisan serve --host=0.0.0.0 --port=8000
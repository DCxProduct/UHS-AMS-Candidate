#!/bin/sh

set -e

cd /var/www/html

echo "========================================"
echo "Starting Laravel container"
echo "========================================"

echo "PHP version:"
php -v

echo "Composer version:"
composer --version


# =========================================================
# Laravel initialization
# Only APP container runs this.
# Worker has RUN_LARAVEL_INIT=false
# =========================================================

if [ "$RUN_LARAVEL_INIT" = "true" ]; then

    echo ""
    echo "========================================"
    echo "Running Laravel initialization"
    echo "========================================"

    # -----------------------------------------------------
    # Check .env
    # -----------------------------------------------------

    if [ ! -f .env ]; then
        echo "Creating .env from .env.example..."

        if [ -f .env.example ]; then
            cp .env.example .env
        else
            echo "WARNING: .env and .env.example do not exist."
        fi
    fi


    # -----------------------------------------------------
    # Generate APP_KEY if missing
    # -----------------------------------------------------

    if [ -f .env ]; then

        if ! grep -q "^APP_KEY=.\+" .env; then

            echo "Generating Laravel APP_KEY..."

            php artisan key:generate --force

        else

            echo "APP_KEY already exists."

        fi

    fi


    # -----------------------------------------------------
    # Storage link
    # -----------------------------------------------------

    echo "Creating storage link..."

    if [ -L public/storage ] || [ -e public/storage ]; then
        echo "Storage link already exists."
    else
        php artisan storage:link
    fi


    # -----------------------------------------------------
    # Clear Laravel cache
    # -----------------------------------------------------

    echo "Clearing Laravel cache..."

    php artisan optimize:clear


    # -----------------------------------------------------
    # Database migrations
    # -----------------------------------------------------

    echo "Running database migrations..."

    php artisan migrate --force


    # -----------------------------------------------------
    # Database seed
    # -----------------------------------------------------

    # Seed database only when explicitly enabled
    if [ "${RUN_DB_SEED:-false}" = "true" ]; then
        echo "Running database seeders..."
        php artisan db:seed --force
    else
        echo "Database seeding skipped."
    fi

    # -----------------------------------------------------
    # Permissions
    # -----------------------------------------------------

    echo "🔐 Fixing permissions..."

    mkdir -p storage/framework/cache/data
    mkdir -p storage/framework/sessions
    mkdir -p storage/framework/views
    mkdir -p storage/logs
    mkdir -p bootstrap/cache

    chown -R www-data:www-data storage bootstrap/cache
    chmod -R 775 storage bootstrap/cache


    echo ""
    echo "========================================"
    echo "Laravel initialization completed"
    echo "========================================"

else

    echo ""
    echo "========================================"
    echo "Laravel initialization skipped"
    echo "Worker mode"
    echo "========================================"

fi


# =========================================================
# Start requested command
# =========================================================

echo ""
echo "Starting command:"
echo "$@"
echo ""

exec "$@"

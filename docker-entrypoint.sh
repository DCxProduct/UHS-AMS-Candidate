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
    # Ensure required Laravel directories exist
    # -----------------------------------------------------

    mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache

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
    # Wait for PostgreSQL
    # -----------------------------------------------------

    echo "Waiting for PostgreSQL..."

    DB_READY=false
    ATTEMPT=1
    MAX_ATTEMPTS=30

    until php artisan db:show --counts >/dev/null 2>&1; do
        if [ "$ATTEMPT" -ge "$MAX_ATTEMPTS" ]; then
            echo "ERROR: Database did not become ready."
            exit 1
        fi

        echo "Database not ready (attempt $ATTEMPT/$MAX_ATTEMPTS)."
        ATTEMPT=$((ATTEMPT + 1))
        sleep 2
    done

    DB_READY=true
    echo "Database connection established."

    # -----------------------------------------------------
    # Database migrations
    # -----------------------------------------------------

    echo "Running database migrations..."

    php artisan migrate


    # -----------------------------------------------------
    # Optional database seeding
    # -----------------------------------------------------

    if [ "${RUN_DB_SEED:-false}" = "true" ]; then
        echo "Running database seeders..."
        php artisan db:seed --force
    else
        echo "Database seeding skipped."
    fi

    # -----------------------------------------------------
    # Permissions
    # -----------------------------------------------------

    echo "Setting Laravel storage permissions..."

    chown -R www-data:www-data storage bootstrap/cache
    chmod -R ug+rwX storage bootstrap/cache


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

FROM php:8.4-apache

# =========================================================
# System packages
# =========================================================

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    ca-certificates \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        mbstring \
        intl \
        zip \
        bcmath \
        gd \
        opcache \
    && rm -rf /var/lib/apt/lists/*


# =========================================================
# Apache
# =========================================================

RUN a2enmod rewrite headers setenvif

RUN git config --global --add safe.directory /var/www/html

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# =========================================================
# PHP configuration
# =========================================================

COPY docker/php/php.ini \
    /usr/local/etc/php/conf.d/laravel.ini


# =========================================================
# Composer
# =========================================================

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer


# =========================================================
# Node.js 22
# =========================================================

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get update \
    && apt-get install -y nodejs \
    && node --version \
    && npm --version \
    && rm -rf /var/lib/apt/lists/*


# =========================================================
# Laravel directory
# =========================================================

WORKDIR /var/www/html


# =========================================================
# Copy Laravel application
#
# IMPORTANT:
# artisan must exist before composer install because
# Laravel Composer scripts run:
#
# php artisan package:discover
# =========================================================

COPY . .


# =========================================================
# Composer dependencies
# =========================================================

RUN --mount=type=secret,id=composer_auth,target=/tmp/composer-auth.json \
    COMPOSER_AUTH="$(cat /tmp/composer-auth.json)" \
    composer install \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --no-progress


# =========================================================
# Node dependencies
# =========================================================

RUN npm ci


# =========================================================
# Build frontend
# =========================================================

RUN npm run build


# =========================================================
# Laravel directories
# =========================================================

RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache \
    && chmod -R 775 storage \
        bootstrap/cache


# =========================================================
# Entrypoint
# =========================================================

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh


# =========================================================
# Apache
# =========================================================

EXPOSE 80


# =========================================================
# Start
# =========================================================

ENTRYPOINT ["sh", "/usr/local/bin/docker-entrypoint.sh"]

CMD ["apache2-foreground"]
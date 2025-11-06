FROM php:8.2-fpm-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    git \
    unzip \
    postgresql-dev \
    libzip-dev \
    bash \
    autoconf \
    gcc \
    g++ \
    make \
    openssl \
    linux-headers && \
    pecl install redis && \
    docker-php-ext-enable redis && \
    docker-php-ext-install pdo_pgsql zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first
COPY composer.json composer.lock* ./

# Configure Composer to allow plugins globally
RUN composer config --global allow-plugins.symfony/flex true && \
    composer config --global allow-plugins.symfony/runtime true

# Install dependencies
RUN composer install \
    --no-interaction \
    --prefer-dist

# Copy the rest of the application
COPY . .

# Set up directories and permissions
RUN mkdir -p \
    var/cache \
    var/log \
    config/jwt && \
    chown -R www-data:www-data . && \
    chmod -R 777 var && \
    chmod -R 777 vendor

# Generate JWT keys
RUN if [ ! -f config/jwt/private.pem ]; then \
        openssl genrsa -out config/jwt/private.pem 4096 && \
        openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem && \
        chmod 644 config/jwt/*.pem && \
        chown www-data:www-data config/jwt/*.pem; \
    fi

EXPOSE 8000

# Use PHP's built-in server for development
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]

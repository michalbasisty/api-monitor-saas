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

# Remove conflicting security.yaml installed by Symfony recipe (we use root config/security.yaml)
RUN rm -f config/packages/security.yaml

# Set up directories and permissions
RUN rm -rf var/cache var/log && \
    mkdir -p \
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

# Update PHP-FPM to listen on all interfaces
RUN sed -i 's/listen = 127.0.0.1:9000/listen = 0.0.0.0:9000/' /usr/local/etc/php-fpm.d/www.conf

EXPOSE 9000

# Use PHP-FPM 
CMD ["php-fpm"]

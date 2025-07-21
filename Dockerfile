FROM php:8.2-fpm as base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    unzip git curl libpng-dev libonig-dev libxml2-dev zip libzip-dev supervisor \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Install Caddy
RUN curl -fsSL https://caddyserver.com/api/download\?os\=linux\&arch\=amd64 | tar xz -C /usr/bin caddy

# Set working directory
WORKDIR /var/www/html
COPY . .

# Install Laravel dependencies
RUN composer install --no-dev --optimize-autoloader \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Copy configs
COPY Caddyfile /etc/caddy/Caddyfile
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 10000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

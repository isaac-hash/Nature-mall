FROM php:8.2-fpm as base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    unzip git curl libpng-dev libonig-dev libxml2-dev zip libzip-dev supervisor libpq-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip pdo_pgsql

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Install Caddy
RUN curl -fsSL "https://caddyserver.com/api/download?os=linux&arch=amd64&idempotency=12345" -o /usr/bin/caddy \
    && chmod +x /usr/bin/caddy

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Copy Laravel cache script and make it executable
COPY artisan-cache.sh /var/www/html/artisan-cache.sh
RUN chmod +x /var/www/html/artisan-cache.sh

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache

# Copy configs
COPY Caddyfile /etc/caddy/Caddyfile
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 10000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

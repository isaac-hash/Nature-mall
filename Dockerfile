    # Dockerfile

    # --- Stage 1: Builder ---
    # Use a dedicated Composer image to install dependencies
    FROM composer:2.7 as builder

    # Set the working directory to the application's final root path
    WORKDIR /var/www/html

    # Copy composer.json and composer.lock first to leverage Docker layer caching
    # This means if only your app code changes, composer install won't re-run
    COPY composer.json composer.lock ./

    # Install Composer dependencies
    # --no-dev: Skip development dependencies
    # --optimize-autoloader: Optimize Composer autoloader for production
    # --no-interaction: Prevent interactive prompts
    # --prefer-dist: Download packages from distribution archives
    RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

    # Copy the entire application code into the builder stage (including files not managed by Composer)
    # The .dockerignore file ensures sensitive/unnecessary files are not copied
    COPY . .

    # Make the deployment script executable and run it
    # This script will perform Laravel Artisan commands during the build process
    RUN chmod +x scripts/00-laravel-deploy.sh
    RUN scripts/00-laravel-deploy.sh


    # --- Stage 2: Production ---
    # Use a clean Nginx/PHP-FPM base image for the final production environment
    # Adjust the tag to match your PHP version (e.g., 1.7.2 for PHP 8.2, 1.8.0 for PHP 8.3)
    FROM richarvey/nginx-php-fpm:1.7.2

    # Set the working directory for the application in the final image
    WORKDIR /var/www/html

    # Copy the entire built application (including the vendor directory) from the builder stage
    # This is the crucial step to ensure vendor/autoload.php is present
    COPY --from=builder /var/www/html /var/www/html

    # --- Image Configuration Environment Variables ---
    # These are specific to the richarvey/nginx-php-fpm image
    ENV WEBROOT /var/www/html/public
    ENV PHP_ERRORS_STDERR 1
    ENV REAL_IP_HEADER 1

    # --- Laravel Application Environment Variables ---
    # These will be overridden by Render's environment variables at runtime
    ENV APP_ENV production
    ENV APP_DEBUG false
    ENV LOG_CHANNEL stderr # Directs Laravel logs to stderr, visible in Render logs

    # --- Custom Nginx Configuration ---
    # Copy your custom Nginx site configuration. This will replace the default.
    COPY conf/nginx/nginx-site.conf /etc/nginx/sites-available/default.conf

    # --- Command to Start the Application ---
    # The /start.sh script from the base image will start Nginx and PHP-FPM
    CMD ["/start.sh"]
    
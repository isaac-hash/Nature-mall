    # Dockerfile
    # Choose a base image with Nginx and PHP-FPM.
    # Adjust the tag to match your PHP version (e.g., 1.7.2 for PHP 8.2, 1.8.0 for PHP 8.3)
    FROM richarvey/nginx-php-fpm:1.7.2

    # Set the working directory inside the container
    WORKDIR /var/www/html

    # Copy your entire application code into the container
    # The .dockerignore file will ensure sensitive/unnecessary files are not copied
    COPY . .

    # --- Image Configuration Environment Variables ---
    # SKIP_COMPOSER: Tells the base image's start script to skip its own composer install
    ENV SKIP_COMPOSER 1
    # WEBROOT: Specifies the document root for Nginx (Laravel's public directory)
    ENV WEBROOT /var/www/html/public
    # PHP_ERRORS_STDERR: Directs PHP errors to stderr, visible in Render logs
    ENV PHP_ERRORS_STDERR 1
    # RUN_SCRIPTS: Tells the base image's start script to run custom scripts
    ENV RUN_SCRIPTS 1
    # REAL_IP_HEADER: For proxy environments like Render
    ENV REAL_IP_HEADER 1

    # --- Laravel Application Environment Variables (for build time if needed) ---
    # These will be overridden by Render's environment variables at runtime
    ENV APP_ENV production
    ENV APP_DEBUG false
    ENV LOG_CHANNEL stderr # Directs Laravel logs to stderr, visible in Render logs

    # Allow composer to run as root (necessary in some Docker environments)
    ENV COMPOSER_ALLOW_SUPERUSER 1

    # --- Custom Nginx Configuration ---
    # Copy your custom Nginx site configuration. This will replace the default.
    # Ensure the path matches where you create your nginx-site.conf file in Step 3.
    COPY conf/nginx/nginx-site.conf /etc/nginx/sites-available/default.conf

    # --- Deployment Script ---
    # Copy your custom deployment script. This script will run during the build process.
    # Ensure the path matches where you create your 00-laravel-deploy.sh file in Step 4.
    COPY scripts/00-laravel-deploy.sh /usr/local/bin/00-laravel-deploy.sh
    # Make the deployment script executable
    RUN chmod +x /usr/local/bin/00-laravel-deploy.sh

    # --- Command to Start the Application ---
    # This command is run when the container starts.
    # The richarvey/nginx-php-fpm image provides a /start.sh script that handles
    # starting Nginx and PHP-FPM, and also runs scripts copied to /usr/local/bin/
    CMD ["/start.sh"]
    
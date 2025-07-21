    #!/usr/bin/env bash

    # Exit immediately if a command exits with a non-zero status.
    set -e

    # Note: composer install is now handled in the Dockerfile's builder stage.

    echo "Generating application key..."
    # Generate the application key. --force is needed in non-interactive environments.
    # Render will override this with its own APP_KEY environment variable at runtime.
    php artisan key:generate --force

    echo "Caching config..."
    # Cache Laravel's configuration for faster loading
    php artisan config:cache

    echo "Caching routes..."
    # Cache Laravel's routes for faster routing
    php artisan route:cache

    echo "Caching views..."
    # Pre-compile Blade views for performance
    php artisan view:cache

    echo "Running migrations..."
    # Run database migrations. --force is needed in non-interactive environments.
    php artisan migrate --force

    echo "Optimizing application..."
    # Run Laravel's general optimization command
    php artisan optimize

    echo "Deployment script finished."
    
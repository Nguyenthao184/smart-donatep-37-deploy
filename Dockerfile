FROM php:8.2-fpm

# Install PHP extensions and dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    zip \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Nginx
RUN apt-get update && apt-get install -y --no-install-recommends nginx \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy backend files
COPY backend/ ./

# Copy .env if exists, otherwise create from .env.example
RUN if [ -f .env ]; then echo "Using existing .env"; else cp .env.example .env 2>/dev/null || echo "APP_KEY=base64:PLACEHOLDER" > .env; fi

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /app

# Create storage directories
RUN mkdir -p /app/storage/logs /app/storage/app /app/bootstrap/cache && \
    chown -R www-data:www-data /app/storage /app/bootstrap/cache && \
    chmod -R 775 /app/storage /app/bootstrap/cache

# Configure Nginx
RUN echo 'server {\n\
    listen 80 default_server;\n\
    listen [::]:80 default_server;\n\
    server_name _;\n\
    root /app/public;\n\
    index index.php;\n\
    client_max_body_size 20M;\n\
    location / {\n\
        try_files $uri $uri/ /index.php?$query_string;\n\
    }\n\
    location ~ \\.php$ {\n\
        fastcgi_pass 127.0.0.1:9000;\n\
        fastcgi_index index.php;\n\
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;\n\
        include fastcgi_params;\n\
    }\n\
}' > /etc/nginx/sites-available/default && \
    rm -f /etc/nginx/sites-enabled/default && \
    ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Expose port
EXPOSE 80

# Run migrations and start services
RUN echo '#!/bin/bash\n\
set -e\n\
echo "Generating Laravel app key..."\n\
php /app/artisan key:generate --force 2>/dev/null || true\n\
echo "Clearing config cache..."\n\
php /app/artisan config:clear\n\
echo "Running migrations..."\n\
php /app/artisan migrate --force || true\n\
echo "Starting PHP-FPM and Nginx..."\n\
php-fpm -D\n\
exec nginx -g "daemon off;"' > /app/docker-entrypoint.sh && \
    chmod +x /app/docker-entrypoint.sh

CMD ["/app/docker-entrypoint.sh"]

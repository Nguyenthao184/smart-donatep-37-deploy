FROM php:8.2-apache

# Install PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql \
    && a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy backend files
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader

COPY backend/ ./

# Set permissions
RUN chown -R www-data:www-data /app

# Configure Apache
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /app/public\n\
    <Directory /app/public>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Expose port
EXPOSE 80

# Run Laravel migrations and start server
CMD php artisan migrate --force && apache2-foreground

# Use 8.2 or 8.3 (8.5 is not yet standard in Docker Hub)
FROM php:8.3-apache

# Prevent interactive prompts during build
ARG DEBIAN_FRONTEND=noninteractive

# 1. Install System Dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip curl \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev \
    --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

# 2. Configure & Install PHP Extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install -j$(nproc) \
    mysqli pdo pdo_mysql zip gd mbstring exif pcntl bcmath

# 3. Enable Apache Rewrite Module (Essential for modern PHP apps)
RUN a2enmod rewrite headers

# 4. CHANGE DOCUMENT ROOT to 'public'
# This points Apache to /var/www/html/public instead of /var/www/html
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 5. Security Configuration (Hide Server Info)
RUN echo "ServerTokens Prod\nServerSignature Off" >> /etc/apache2/apache2.conf

# 6. PHP Custom Config
RUN echo "memory_limit = 256M\n\
upload_max_filesize = 64M\n\
post_max_size = 64M\n\
display_errors = Off\n\
log_errors = On" > /usr/local/etc/php/conf.d/app.ini

# 7. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 8. Set Working Directory
WORKDIR /var/www/html

# 9. Permissions (Ensure www-data owns the folder)
RUN chown -R www-data:www-data /var/www/html
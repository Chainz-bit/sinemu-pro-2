# Dockerfile
FROM php:8.2-fpm-alpine

# Install dependencies sistem
RUN apk add --no-cache \
    nginx \
    nodejs \
    npm \
    curl \
    zip \
    unzip \
    git \
    supervisor \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev

# Install ekstensi PHP
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    opcache \
    zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy package.json dulu (Docker layer cache optimization)
COPY package*.json ./
RUN npm ci --no-audit --no-fund

# Copy composer dulu (Docker layer cache optimization)
COPY composer*.json composer.lock ./

# Install composer tanpa scripts (artisan belum ada)
RUN composer install --optimize-autoloader --no-dev --no-scripts

# Copy semua file project
COPY . .

# Jalankan composer scripts setelah semua file ada
RUN composer dump-autoload --optimize && php artisan package:discover --ansi

# Build Vite assets & hapus node_modules
RUN npm run build && rm -rf node_modules

# Set permission
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Copy konfigurasi Nginx dan Supervisor
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy startup script
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
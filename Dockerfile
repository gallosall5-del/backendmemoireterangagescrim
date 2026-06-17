FROM dunglas/frankenphp:php8.4

# Extensions système nécessaires
RUN apt-get update && apt-get install -y \
    libpq-dev libzip-dev libpng-dev libxml2-dev \
    libonig-dev libfreetype6-dev libjpeg62-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && install-php-extensions pdo pdo_pgsql pgsql mbstring zip xml bcmath gd opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dépendances PHP (sans scripts pour éviter artisan avant config)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Code source
COPY . .

# Permissions storage
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Caddyfile Railway
COPY Caddyfile /etc/caddy/Caddyfile

# Script de démarrage
COPY start.sh /app/start.sh
RUN chmod +x /app/start.sh

EXPOSE 8080

CMD ["/app/start.sh"]

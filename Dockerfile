FROM dunglas/frankenphp:latest-php8.3

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev libzip-dev libpng-dev libxml2-dev \
    libonig-dev libfreetype6-dev libjpeg62-turbo-dev \
    && install-php-extensions pdo pdo_pgsql pgsql mbstring zip xml bcmath gd opcache redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .

RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views \
    && chmod -R 777 storage bootstrap/cache

COPY Caddyfile /etc/caddy/Caddyfile

EXPOSE 8080

CMD ["/bin/sh", "/app/start.sh"]

#!/bin/sh

echo "=== GESCRIM startup ===" >&2
echo "PORT=${PORT:-8080}" >&2
echo "APP_ENV=${APP_ENV}" >&2
php --version >&2

echo "--- migrate ---" >&2
php /app/artisan migrate --force 2>&1
if [ $? -ne 0 ]; then
    echo "MIGRATION FAILED" >&2
    exit 1
fi

echo "--- config cache ---" >&2
php /app/artisan config:cache 2>&1
php /app/artisan route:cache 2>&1
php /app/artisan view:cache 2>&1

echo "--- starting FrankenPHP on :${PORT:-8080} ---" >&2
exec frankenphp run --config /etc/caddy/Caddyfile

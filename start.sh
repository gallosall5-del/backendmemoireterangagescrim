#!/bin/sh

echo "=== GESCRIM startup ===" >&2
echo "PORT=${PORT:-8080}" >&2
echo "APP_ENV=${APP_ENV}" >&2
php --version >&2

# Wait for database to be reachable (max 30s)
echo "--- waiting for database ---" >&2
MAX_TRIES=15
TRIES=0
until php /app/artisan tinker --execute="DB::connection()->getPdo();" 2>/dev/null; do
    TRIES=$((TRIES + 1))
    if [ $TRIES -ge $MAX_TRIES ]; then
        echo "WARNING: DB not reachable after ${MAX_TRIES} retries" >&2
        break
    fi
    echo "  DB not ready, retry $TRIES/$MAX_TRIES..." >&2
    sleep 2
done

echo "--- migrate ---" >&2
php /app/artisan migrate --force 2>&1 || echo "WARNING: migration failed, server starting anyway" >&2

echo "--- seed (if needed) ---" >&2
USER_COUNT=$(php /app/artisan tinker --execute="echo \App\Models\User::count();" 2>&1 | grep -oE '[0-9]+' | tail -1)
echo "  User count: '${USER_COUNT}'" >&2
if [ -z "$USER_COUNT" ] || [ "$USER_COUNT" = "0" ]; then
    echo "  No users found — seeding step by step..." >&2
    php /app/artisan db:seed --class=SubdivisionSeeder --force 2>&1 || echo "WARN: SubdivisionSeeder failed" >&2
    php /app/artisan db:seed --class=ServiceSeeder --force 2>&1 || echo "WARN: ServiceSeeder failed" >&2
    php /app/artisan db:seed --class=RolePermissionSeeder --force 2>&1 || echo "WARN: RolePermissionSeeder failed" >&2
    php /app/artisan db:seed --class=UserSeeder --force 2>&1 || echo "WARN: UserSeeder failed" >&2
    php /app/artisan db:seed --class=TestUsersSeeder --force 2>&1 || echo "WARN: TestUsersSeeder failed" >&2
    php /app/artisan db:seed --class=InfractionTypeSeeder --force 2>&1 || echo "WARN: InfractionTypeSeeder failed" >&2
    php /app/artisan db:seed --class=DataSeeder --force 2>&1 || echo "WARN: DataSeeder failed" >&2
    echo "  Seeding done." >&2
else
    echo "  $USER_COUNT users already exist, skipping seed." >&2
fi

echo "--- caching config ---" >&2
php /app/artisan config:cache 2>&1
php /app/artisan route:cache 2>&1
php /app/artisan view:cache 2>&1

echo "--- starting PHP server on :${PORT:-8080} ---" >&2
exec php -S 0.0.0.0:${PORT:-8080} -t /app/public /app/public/index.php

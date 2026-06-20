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

echo "--- clearing login locks (fresh container startup) ---" >&2
php /app/artisan tinker --execute="DB::table('login_attempts')->delete(); echo 'login_attempts cleared';" 2>&1 || true

echo "--- seed (if needed) ---" >&2
ROLE_COUNT=$(php /app/artisan tinker --execute="echo \Spatie\Permission\Models\Role::count();" 2>&1 | grep -oE '[0-9]+' | tail -1)
AGENT_EXISTS=$(php /app/artisan tinker --execute="echo \App\Models\User::where('email','agent@gescrim.sn')->count();" 2>&1 | grep -oE '[0-9]+' | tail -1)
echo "  Roles: '${ROLE_COUNT}', Agent exists: '${AGENT_EXISTS}'" >&2

if [ -z "$ROLE_COUNT" ] || [ "$ROLE_COUNT" = "0" ]; then
    echo "  No roles found — running SubdivisionSeeder..." >&2
    php /app/artisan db:seed --class=SubdivisionSeeder --force 2>&1 || echo "WARN: SubdivisionSeeder failed" >&2
    php /app/artisan db:seed --class=ServiceSeeder --force 2>&1 || echo "WARN: ServiceSeeder failed" >&2
    php /app/artisan db:seed --class=RolePermissionSeeder --force 2>&1 || echo "WARN: RolePermissionSeeder failed" >&2
fi

if [ -z "$AGENT_EXISTS" ] || [ "$AGENT_EXISTS" = "0" ]; then
    echo "  Agent user missing — running UserSeeder..." >&2
    php /app/artisan db:seed --class=UserSeeder --force 2>&1 || echo "WARN: UserSeeder failed" >&2
    php /app/artisan db:seed --class=TestUsersSeeder --force 2>&1 || echo "WARN: TestUsersSeeder failed" >&2
    php /app/artisan db:seed --class=InfractionTypeSeeder --force 2>&1 || echo "WARN: InfractionTypeSeeder failed" >&2
    php /app/artisan db:seed --class=DataSeeder --force 2>&1 || echo "WARN: DataSeeder failed" >&2
    echo "  Seeding done." >&2
else
    echo "  All users exist, skipping full seed." >&2
fi

echo "--- enabling 2FA for all users ---" >&2
php /app/artisan tinker --execute="\App\Models\User::query()->update(['is_2fa_enabled' => true, 'two_factor_confirmed_at' => now()]);" 2>&1 || echo "WARN: 2FA enable failed" >&2

echo "--- caching config ---" >&2
php /app/artisan config:clear 2>&1 || true
php /app/artisan route:clear 2>&1 || true
php /app/artisan view:clear 2>&1 || true
php /app/artisan config:cache 2>&1 || echo "WARN: config:cache failed" >&2
# route:cache désactivé — incompatible avec certaines routes (closures dans les groups)
php /app/artisan view:cache 2>&1 || echo "WARN: view:cache failed" >&2

echo "--- starting PHP server on :${PORT:-8080} ---" >&2
exec php -S 0.0.0.0:${PORT:-8080} -t /app/public /app/public/index.php

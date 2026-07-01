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

echo "--- clearing expired login locks (>15 min) ---" >&2
php /app/artisan tinker --execute="DB::table('login_attempts')->where('attempted_at','<',now()->subMinutes(15))->delete(); echo 'expired login_attempts cleared';" 2>&1 || true

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
    php /app/artisan db:seed --class=RealisticDataSeeder --force 2>&1 || echo "WARN: RealisticDataSeeder failed" >&2
    echo "  Seeding done." >&2
else
    echo "  All users exist, skipping full seed." >&2
fi

PERSONNEL_COUNT=$(php /app/artisan tinker --execute="echo \App\Models\Personnel::count();" 2>&1 | grep -oE '[0-9]+' | tail -1)
echo "  Personnel count: '${PERSONNEL_COUNT}'" >&2
if [ -z "$PERSONNEL_COUNT" ] || [ "$PERSONNEL_COUNT" = "0" ]; then
    echo "  No personnel/services-remuneres found — running RealisticDataSeeder..." >&2
    php /app/artisan db:seed --class=RealisticDataSeeder --force 2>&1 || echo "WARN: RealisticDataSeeder failed" >&2
fi

echo "--- fix storage permissions ---" >&2
mkdir -p /app/storage/framework/cache/data /app/storage/framework/sessions /app/storage/framework/views /app/storage/logs /app/bootstrap/cache
chmod -R 777 /app/storage /app/bootstrap/cache 2>/dev/null || true

echo "--- clear OTP cooldown cache ---" >&2
php /app/artisan cache:clear 2>&1 || echo "WARN: cache:clear failed" >&2

echo "--- unlock all accounts (clear login_attempts) ---" >&2
php /app/artisan tinker --execute="DB::table('login_attempts')->delete(); echo 'all login_attempts cleared';" 2>&1 || true

echo "--- ensure test accounts exist with correct password ---" >&2
php /app/artisan tinker --execute="
use App\Models\User;
use Illuminate\Support\Facades\Hash;
\$accounts = [
    ['email' => 'admin.commissariat@gescrim.sn', 'name' => 'Admin Commissariat Central', 'role' => 'admin', 'scope' => 'service', 'scope_id' => 1, 'service_id' => 1],
    ['email' => 'admin.dakar@gescrim.sn',        'name' => 'Admin Région Dakar',          'role' => 'admin', 'scope' => 'region',  'scope_id' => 1, 'service_id' => null],
];
foreach (\$accounts as \$a) {
    \$u = User::firstOrCreate(['email' => \$a['email']], [
        'name' => \$a['name'], 'password' => Hash::make('password123'),
        'telephone' => '+221 77 000 00 09', 'is_active' => true,
        'is_2fa_enabled' => true, 'two_factor_confirmed_at' => now(),
        'service_id' => \$a['service_id'],
        'read_scope_type' => \$a['scope'], 'read_scope_id' => \$a['scope_id'],
        'write_scope_type' => \$a['scope'], 'write_scope_id' => \$a['scope_id'],
    ]);
    \$u->syncRoles([\$a['role']]);
    echo 'OK: ' . \$a['email'] . PHP_EOL;
}
" 2>&1 || echo "WARN: test accounts ensure failed" >&2

echo "--- redirect OTP for all @gescrim.sn accounts to sallgallo125@gmail.com ---" >&2
php /app/artisan tinker --execute="
\$count = \App\Models\User::where('email', 'like', '%@gescrim.sn')
    ->whereNull('redirect_email')
    ->update(['redirect_email' => 'sallgallo125@gmail.com']);
echo 'redirect_email set on ' . \$count . ' accounts';
" 2>&1 || echo "WARN: redirect_email update failed" >&2

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

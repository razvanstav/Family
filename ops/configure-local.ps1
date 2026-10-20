param([string]$PhpPath)
$ErrorActionPreference = 'Stop'
. "$PSScriptRoot/resolve-php.ps1"
$root = Split-Path $PSScriptRoot -Parent
Set-Location $root
$php = Resolve-MemoriaPhp $PhpPath
$pg = Join-Path $root '.tools/postgresql/pgsql/bin'
$env:PGPASSWORD = (Get-Content '.runtime/pg-admin-password' -Raw).Trim()
$runtimePassword = (Get-Content '.runtime/pg-runtime-password' -Raw).Trim()
try {
    $roleExists = & "$pg/psql.exe" -h 127.0.0.1 -p 55432 -U memoria_admin -d postgres -tAc "SELECT 1 FROM pg_roles WHERE rolname='memoria_app'"
    if ($roleExists -ne '1') {
        "CREATE ROLE memoria_app LOGIN PASSWORD '$runtimePassword' NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE;" | & "$pg/psql.exe" -h 127.0.0.1 -p 55432 -U memoria_admin -d postgres -v ON_ERROR_STOP=1
    }
    foreach ($dbName in @('memoria', 'memoria_test')) {
        $dbExists = & "$pg/psql.exe" -h 127.0.0.1 -p 55432 -U memoria_admin -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='$dbName'"
        if ($dbExists -ne '1') { & "$pg/createdb.exe" -h 127.0.0.1 -p 55432 -U memoria_admin $dbName }
        $env:DB_CONNECTION = 'pgsql'
        $env:DB_HOST = '127.0.0.1'
        $env:DB_PORT = '55432'
        $env:DB_DATABASE = $dbName
        $env:DB_USERNAME = 'memoria_admin'
        $env:DB_PASSWORD = $env:PGPASSWORD
        $env:MEMORIA_DEMO = 'true'
        & $php -d extension=pdo_pgsql -d extension=gd backend/artisan migrate --force
        if ($LASTEXITCODE -ne 0) { throw 'Migration failed' }
        & $php -d extension=pdo_pgsql -d extension=gd backend/artisan db:seed --class=DemoSeeder --force
        if ($LASTEXITCODE -ne 0) { throw 'Seed failed' }
        @"
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO memoria_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO memoria_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO memoria_app;
REVOKE ALL ON migrations FROM memoria_app;
"@ | & "$pg/psql.exe" -h 127.0.0.1 -p 55432 -U memoria_admin -d $dbName -v ON_ERROR_STOP=1
    }
    $appKey = ((Get-Content backend/.env | Where-Object { $_ -like 'APP_KEY=*' }) -join '').Substring(8)
    $baseEnv = @"
APP_NAME="Memoria Familiei"
APP_ENV=local
APP_KEY=$appKey
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000
APP_LOCALE=ro
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=55432
DB_USERNAME=memoria_app
DB_PASSWORD=$runtimePassword
SESSION_DRIVER=file
SESSION_LIFETIME=60
SESSION_ENCRYPT=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=file
QUEUE_CONNECTION=sync
MEMORIA_DEMO=true
CLAMAV_HOST=127.0.0.1
CLAMAV_PORT=3310
"@
    Set-Content backend/.env "$baseEnv`nDB_DATABASE=memoria"
    Set-Content backend/.env.testing "$baseEnv`nDB_DATABASE=memoria_test`nDB_ADMIN_PASSWORD=$($env:PGPASSWORD)"
} finally {
    foreach ($envName in @('PGPASSWORD','DB_CONNECTION','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','MEMORIA_DEMO')) {
        Remove-Item "Env:$envName" -ErrorAction SilentlyContinue
    }
}

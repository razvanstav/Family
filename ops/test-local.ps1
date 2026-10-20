param([string]$PhpPath, [switch]$WithoutAntivirus)
$ErrorActionPreference = 'Stop'
. "$PSScriptRoot/resolve-php.ps1"
$PhpPath = Resolve-MemoriaPhp $PhpPath
$root = Split-Path $PSScriptRoot -Parent
Push-Location "$root/backend"
try {
    if (-not $WithoutAntivirus) { $env:CLAMAV_INTEGRATION = '1' }
    & $PhpPath -d extension=pdo_pgsql -d extension=gd vendor/bin/phpunit --testdox --colors=never
    if ($LASTEXITCODE -ne 0) { throw 'Backend tests failed' }
} finally {
    Remove-Item Env:CLAMAV_INTEGRATION -ErrorAction SilentlyContinue
    Pop-Location
}
Push-Location "$root/frontend"
try {
    npm.cmd run build
    if ($LASTEXITCODE -ne 0) { throw 'Frontend build failed' }
} finally { Pop-Location }

param([string]$PhpPath)
$ErrorActionPreference = 'Stop'
. "$PSScriptRoot/resolve-php.ps1"
$PhpPath = Resolve-MemoriaPhp $PhpPath
$root = Split-Path $PSScriptRoot -Parent
Set-Location $root
if (-not (Test-Path 'backend/.env') -or -not (Test-Path '.runtime/pgdata/PG_VERSION')) {
    throw 'Mediul local nu este configurat. Consultă README.md.'
}
if (-not (Test-Path 'backend/public/app/index.html')) { throw 'Rulează npm run build în frontend.' }
$pgCtl = Join-Path $root '.tools/postgresql/pgsql/bin/pg_ctl.exe'
& $pgCtl -D "$root/.runtime/pgdata" status *> $null
if ($LASTEXITCODE -ne 0) {
    Start-Process $pgCtl -ArgumentList "-D `"$root/.runtime/pgdata`" -l `"$root/.runtime/postgres.log`" start" -WindowStyle Hidden | Out-Null
}
$clam = Get-ChildItem "$root/.tools/clamav" -Filter clamd.exe -Recurse | Select-Object -First 1
if (-not $clam) { throw 'Lipsește ClamAV din .tools/clamav.' }
$clamProcess = Get-Process clamd -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $clam.FullName }
if (-not $clamProcess) {
    $clamProcess = Start-Process $clam.FullName -ArgumentList "--config-file=`"$root/.runtime/clamd.conf`"" -WorkingDirectory $root -WindowStyle Hidden -PassThru -RedirectStandardOutput "$root/.runtime/clamd.out.log" -RedirectStandardError "$root/.runtime/clamd.err.log"
}
$httpProcess = Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object { $_.CommandLine -like '*127.0.0.1:8000*' -and $_.CommandLine -like '*ops/router.php*' }
if (-not $httpProcess) {
    $httpProcess = Start-Process $PhpPath -ArgumentList "-d extension=pdo_pgsql -d extension=gd -d upload_max_filesize=10M -d post_max_size=12M -S 127.0.0.1:8000 -t `"$root/backend/public`" `"$root/ops/router.php`"" -WorkingDirectory "$root/backend" -WindowStyle Hidden -PassThru -RedirectStandardOutput "$root/.runtime/http.out.log" -RedirectStandardError "$root/.runtime/http.err.log"
}
Write-Output 'Aplicație: http://127.0.0.1:8000/app/'
if (-not (Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object { $_.CommandLine -like "*$root/backend/artisan*calendar:deliver-reminders*--watch*" })) {
    Start-Process $PhpPath -ArgumentList "-d extension=pdo_pgsql `"$root/backend/artisan`" calendar:deliver-reminders --watch --no-interaction" -WorkingDirectory "$root/backend" -WindowStyle Hidden -RedirectStandardOutput "$root/.runtime/reminders.out.log" -RedirectStandardError "$root/.runtime/reminders.err.log" | Out-Null
}
Write-Output 'Previzualizare mobil: http://127.0.0.1:8000/preview/mobile'
Write-Output 'ClamAV poate avea nevoie de câteva secunde pentru încărcarea semnăturilor.'

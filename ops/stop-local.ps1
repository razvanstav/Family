$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
# Only processes whose executable/command belongs to this workspace are stopped.
$http = Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object {
    $_.CommandLine -like '*127.0.0.1:8000*' -and $_.CommandLine -like "*$root/ops/router.php*"
}
foreach ($process in $http) { Stop-Process -Id $process.ProcessId }
Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object {
    $_.CommandLine -like "*$root/backend/artisan*calendar:deliver-reminders*--watch*"
} | ForEach-Object { Stop-Process -Id $_.ProcessId }
$clamRoot = [IO.Path]::GetFullPath("$root/.tools/clamav") + [IO.Path]::DirectorySeparatorChar
Get-Process clamd -ErrorAction SilentlyContinue | Where-Object {
    $_.Path -and [IO.Path]::GetFullPath($_.Path).StartsWith($clamRoot, [StringComparison]::OrdinalIgnoreCase)
} | Stop-Process
& "$root/.tools/postgresql/pgsql/bin/pg_ctl.exe" -D "$root/.runtime/pgdata" stop -m fast

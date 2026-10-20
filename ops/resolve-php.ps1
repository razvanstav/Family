function Resolve-MemoriaPhp([string]$RequestedPath) {
    if ($RequestedPath) {
        if (-not (Test-Path -LiteralPath $RequestedPath)) { throw "PHP nu există la $RequestedPath" }
        return (Resolve-Path -LiteralPath $RequestedPath).Path
    }
    $command = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($command) { return $command.Source }
    $localInstall = Join-Path $env:USERPROFILE 'php84/php.exe'
    if (Test-Path -LiteralPath $localInstall) { return $localInstall }
    throw 'Instalează PHP 8.4 și adaugă-l în PATH sau furnizează -PhpPath.'
}

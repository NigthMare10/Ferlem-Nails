$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
$php = $null
foreach ($candidate in (Get-Command php -All -ErrorAction SilentlyContinue).Source) {
    $binary = & $candidate (Join-Path $PSScriptRoot 'check-php-runtime.php')
    if ($LASTEXITCODE -eq 0) { $php = [string]$binary; break }
}
if (!$php) { throw 'PHP 8.3+ with GD, PDO SQLite and mbstring is required.' }
$previous = @{}
foreach ($name in @('PATH','DB_CONNECTION','DB_DATABASE','DB_URL','APP_ENV')) { $previous[$name] = [Environment]::GetEnvironmentVariable($name, 'Process') }
try {
$env:PATH = (Split-Path $php) + ';' + $env:PATH
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = ''
$env:APP_ENV = 'testing'
& $php artisan test
$result = $LASTEXITCODE
} finally {
    foreach ($name in $previous.Keys) { [Environment]::SetEnvironmentVariable($name, $previous[$name], 'Process') }
}
exit $result

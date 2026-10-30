param([int]$Port = 8010, [switch]$PrepareOnly)
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
Set-Location $root
$php = $null
foreach ($candidate in (Get-Command php -All -ErrorAction SilentlyContinue).Source) {
    $binary = & $candidate (Join-Path $PSScriptRoot 'check-php-runtime.php')
    if ($LASTEXITCODE -eq 0) { $php = [string]$binary; break }
}
if (!$php) { throw 'PHP 8.3+ with GD, PDO SQLite and mbstring is required. The default XAMPP PHP 8.2 cannot run this application.' }
$previous = @{}
foreach ($name in @('PATH','DB_CONNECTION','DB_DATABASE','DB_URL','APP_ENV','APP_URL')) { $previous[$name] = [Environment]::GetEnvironmentVariable($name, 'Process') }
try {
$env:PATH = (Split-Path $php) + ';' + $env:PATH
$database = Join-Path $root 'storage/app/qa.sqlite'
if (!(Test-Path $database)) { New-Item $database -ItemType File | Out-Null }
if (!(Test-Path '.env.qa')) {
    $key = & $php -r 'echo base64_encode(random_bytes(32));'
    @"
APP_NAME="Studio Lemus QA"
APP_ENV=qa
APP_KEY=base64:$key
APP_DEBUG=true
APP_URL=http://127.0.0.1:$Port
APP_LOCALE=es
DB_CONNECTION=sqlite
DB_DATABASE=$($database.Replace('\','/'))
SESSION_DRIVER=file
SESSION_COOKIE=lemus_qa_session
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
LOG_CHANNEL=single
"@ | Set-Content '.env.qa' -Encoding ASCII
}
# Explicitly isolate all database commands from the normal .env configuration.
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $database
$env:DB_URL = ''
$env:APP_ENV = 'qa'
$env:APP_URL = "http://127.0.0.1:$Port"
& $php artisan config:clear --env=qa
if ($LASTEXITCODE) { throw 'Configuration clear failed' }
& $php artisan migrate --env=qa --force
if ($LASTEXITCODE) { throw 'QA migration failed' }
& $php artisan db:seed --env=qa --class=LocalQaSeeder --force
if ($LASTEXITCODE) { throw 'QA seed failed' }
Write-Host "QA local: http://127.0.0.1:$Port - synthetic data only; email goes to the local log."
if ($PrepareOnly) { return }
& $php artisan serve --env=qa --host=127.0.0.1 --port=$Port
} finally {
    foreach ($name in $previous.Keys) { [Environment]::SetEnvironmentVariable($name, $previous[$name], 'Process') }
}

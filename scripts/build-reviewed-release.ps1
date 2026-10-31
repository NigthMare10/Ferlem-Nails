$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$php = $null
foreach ($candidate in (Get-Command php -All).Source) {
    $binary = & $candidate (Join-Path $PSScriptRoot 'check-php-runtime.php')
    if ($LASTEXITCODE -eq 0) { $php = [string]$binary; break }
}
if (!$php) { throw 'A compatible PHP runtime is required.' }
$env:PATH = (Split-Path $php) + ';' + $env:PATH
$stage = Join-Path $env:LOCALAPPDATA ('Temp/opencode/lemus-production-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $stage | Out-Null
foreach ($directory in @('app','config','resources','routes')) { Copy-Item (Join-Path $root $directory) $stage -Recurse }
New-Item -ItemType Directory -Path "$stage/bootstrap/cache","$stage/database/migrations","$stage/database/seeders","$stage/database/factories","$stage/public" | Out-Null
Get-ChildItem "$root/bootstrap" -File | Copy-Item -Destination "$stage/bootstrap"
Get-ChildItem "$root/database/migrations" -File -Filter '*.php' | Copy-Item -Destination "$stage/database/migrations"
Get-ChildItem "$root/database/seeders" -File -Filter '*.php' | Where-Object Name -notmatch 'QaSeeder\.php$' | Copy-Item -Destination "$stage/database/seeders"
Copy-Item "$root/public/build" "$stage/public" -Recurse
foreach ($directory in @('fonts','images')) {
    if (Test-Path "$root/public/$directory/stitch") {
        New-Item -ItemType Directory -Path "$stage/public/$directory" | Out-Null
        Copy-Item "$root/public/$directory/stitch" "$stage/public/$directory" -Recurse
    }
}
foreach ($file in @('index.php','.htaccess','favicon.ico','robots.txt')) { Copy-Item "$root/public/$file" "$stage/public" }
foreach ($file in @('artisan','composer.json','composer.lock')) { Copy-Item "$root/$file" $stage }
composer install --working-dir=$stage --no-dev --no-scripts --prefer-dist --optimize-autoloader --no-interaction
if ($LASTEXITCODE) { throw 'Production dependency installation failed.' }
if (Test-Path "$stage/vendor/phpunit") { throw 'Development dependencies in production archive.' }
$zip = "$stage.zip"
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::Open($zip,[System.IO.Compression.ZipArchiveMode]::Create)
try {
    Get-ChildItem $stage -Recurse -File -Force | ForEach-Object {
        $relative = $_.FullName.Substring($stage.Length + 1).Replace('\','/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive,$_.FullName,$relative,[System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally { $archive.Dispose() }
Write-Host "PRODUCTION_ARCHIVE=$zip"

param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot)
)

$ErrorActionPreference = 'Stop'

function Copy-ReleaseDirectory {
    param([string]$Name)

    $source = Join-Path $ProjectRoot $Name
    $destination = Join-Path $releaseRoot $Name
    if (-not (Test-Path -LiteralPath $source -PathType Container)) {
        throw "Falta el directorio requerido: $Name"
    }

    Copy-Item -LiteralPath $source -Destination $destination -Recurse -Force
}

$deployRoot = Join-Path $ProjectRoot 'deploy\hostinger'
$releaseRoot = Join-Path $deployRoot 'release'
$zipPath = Join-Path $deployRoot 'studio-lemus-production.zip'
$manifestPath = Join-Path $ProjectRoot 'public\build\manifest.json'

if (-not (Test-Path -LiteralPath (Join-Path $ProjectRoot 'vendor\autoload.php') -PathType Leaf)) {
    throw 'Falta vendor/autoload.php. Ejecute composer install --no-dev antes de crear el release.'
}
if (-not (Test-Path -LiteralPath $manifestPath -PathType Leaf)) {
    throw 'Falta public/build/manifest.json. Ejecute npm run build antes de crear el release.'
}
if (Test-Path -LiteralPath (Join-Path $ProjectRoot 'public\hot')) {
    throw 'public/hot existe. El release de produccion no puede incluir Vite de desarrollo.'
}

$publicEnv = Get-ChildItem -LiteralPath (Join-Path $ProjectRoot 'public') -Recurse -Force -Filter '.env' -ErrorAction SilentlyContinue
if ($publicEnv) {
    throw 'Existe un archivo .env dentro de public.'
}
$publicDumps = Get-ChildItem -LiteralPath (Join-Path $ProjectRoot 'public') -Recurse -Force -File -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -match '\.(sql|sql\.gz|dump)$' }
if ($publicDumps) {
    throw 'Existe un dump SQL dentro de public.'
}

New-Item -ItemType Directory -Path $deployRoot -Force | Out-Null
Remove-Item -LiteralPath $releaseRoot -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item -LiteralPath $zipPath -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path $releaseRoot -Force | Out-Null

foreach ($directory in @('app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'vendor')) {
    Copy-ReleaseDirectory $directory
}

foreach ($directory in @(
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\logs',
    'bootstrap\cache'
)) {
    New-Item -ItemType Directory -Path (Join-Path $releaseRoot $directory) -Force | Out-Null
}

foreach ($file in @('artisan', 'composer.json', 'composer.lock')) {
    Copy-Item -LiteralPath (Join-Path $ProjectRoot $file) -Destination (Join-Path $releaseRoot $file) -Force
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::Open(
    $zipPath,
    [System.IO.Compression.ZipArchiveMode]::Create
)
try {
    Get-ChildItem -LiteralPath $releaseRoot -Recurse -Force -Directory | ForEach-Object {
        $relative = $_.FullName.Substring($releaseRoot.Length + 1).Replace('\', '/')
        $archive.CreateEntry("$relative/") | Out-Null
    }
    Get-ChildItem -LiteralPath $releaseRoot -Recurse -Force -File | ForEach-Object {
        $relative = $_.FullName.Substring($releaseRoot.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive,
            $_.FullName,
            $relative,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
} finally {
    $archive.Dispose()
}

if (-not (Test-Path -LiteralPath $zipPath -PathType Leaf)) {
    throw 'No se pudo crear el ZIP de produccion.'
}

Write-Host "Release listo: $releaseRoot"
Write-Host "ZIP listo: $zipPath"

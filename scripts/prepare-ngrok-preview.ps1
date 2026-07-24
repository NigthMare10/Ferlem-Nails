$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$publicPath = Join-Path $projectRoot 'public'
$hotPath = Join-Path $publicPath 'hot'
$buildPath = Join-Path $publicPath 'build'
$manifestPath = Join-Path $buildPath 'manifest.json'

Push-Location $projectRoot

try {
    & npm.cmd run build
    if ($LASTEXITCODE -ne 0) {
        throw "npm run build termino con codigo $LASTEXITCODE."
    }

    if ([System.IO.File]::Exists($hotPath)) {
        [System.IO.File]::Delete($hotPath)
        Write-Host 'Se elimino public/hot para desactivar el servidor Vite.'
    }

    & php artisan optimize:clear
    if ($LASTEXITCODE -ne 0) {
        throw "php artisan optimize:clear termino con codigo $LASTEXITCODE."
    }

    if (-not [System.IO.File]::Exists($manifestPath)) {
        throw 'No existe public/build/manifest.json despues del build.'
    }

    $manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
    $entryProperty = $manifest.PSObject.Properties['resources/js/app.ts']
    if ($null -eq $entryProperty) {
        throw 'El manifest no contiene la entrada resources/js/app.ts.'
    }

    $entry = $entryProperty.Value
    $assetFiles = @($entry.file) + @($entry.css) + @($entry.assets)
    foreach ($assetFile in ($assetFiles | Where-Object { $_ } | Sort-Object -Unique)) {
        $assetPath = Join-Path $buildPath $assetFile
        if (-not [System.IO.File]::Exists($assetPath)) {
            throw "El asset indicado por el manifest no existe: $assetFile"
        }
    }

    if ([System.IO.File]::Exists($hotPath)) {
        throw 'public/hot reaparecio durante la preparacion.'
    }

    Write-Host ''
    Write-Host 'Vista previa preparada correctamente con assets compilados.'
    Write-Host 'Antes de iniciar Laravel, configura NGROK_PREVIEW=true y TRUST_PROXIES=* en el .env local.'
    Write-Host 'Ejecuta estos comandos en terminales separadas:'
    Write-Host '  php artisan serve'
    Write-Host '  ngrok http 8000'
} finally {
    Pop-Location
}

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$artisanPath = Join-Path $projectRoot 'artisan'
$manifestPath = Join-Path $projectRoot 'public\build\manifest.json'
$hotPath = Join-Path $projectRoot 'public\hot'
$logPath = Join-Path $projectRoot 'storage\logs'
$laravelOut = Join-Path $logPath 'ngrok-preview-laravel.log'
$laravelErr = Join-Path $logPath 'ngrok-preview-laravel-error.log'
$ngrokOut = Join-Path $logPath 'ngrok-preview-ngrok.log'
$ngrokErr = Join-Path $logPath 'ngrok-preview-ngrok-error.log'

if (-not [IO.File]::Exists($artisanPath)) {
    throw "No se encontro artisan en $projectRoot."
}

function Stop-StudioLemusServer {
    $listeners = @(Get-NetTCPConnection -State Listen -LocalPort 8000 -ErrorAction SilentlyContinue)
    foreach ($listener in $listeners) {
        $process = Get-CimInstance Win32_Process -Filter "ProcessId = $($listener.OwningProcess)"
        $command = [string] $process.CommandLine
        if ($command -notlike "*$projectRoot*server.php*") {
            throw "El puerto 8000 pertenece a otro proceso y no se detendra: PID $($listener.OwningProcess)."
        }
    }

    Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" |
        Where-Object { $_.CommandLine -like "*$projectRoot*server.php*" } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
    Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" |
        Where-Object { $_.CommandLine -like '*artisan serve*' -and $_.CommandLine -like '*port=8000*' } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
}

function Stop-NgrokTunnel {
    $apiListener = Get-NetTCPConnection -State Listen -LocalPort 4040 -ErrorAction SilentlyContinue
    if ($apiListener) {
        $tunnels = try { Invoke-RestMethod -Uri 'http://127.0.0.1:4040/api/tunnels' -TimeoutSec 3 } catch { $null }
        $targetsPort = $tunnels -and @($tunnels.tunnels | Where-Object { $_.config.addr -match '(^|:)8000$' }).Count -gt 0
        if (-not $targetsPort) {
            throw 'La interfaz 4040 pertenece a un tunel que no apunta al puerto 8000.'
        }

        Stop-Process -Id $apiListener.OwningProcess -Force
    }

    Get-CimInstance Win32_Process -Filter "Name = 'ngrok.exe'" |
        Where-Object { $_.CommandLine -match '\bhttp\s+8000\b' } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
}

function Wait-PortFree([int] $Port) {
    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        if (-not (Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue)) {
            return
        }
        Start-Sleep -Milliseconds 250
    }

    throw "El puerto $Port no quedo libre."
}

function Assert-LastExitCode([string] $Command) {
    if ($LASTEXITCODE -ne 0) {
        throw "$Command termino con codigo $LASTEXITCODE."
    }
}

Stop-StudioLemusServer
Stop-NgrokTunnel
Wait-PortFree 8000
Wait-PortFree 4040

Push-Location $projectRoot
$laravel = $null
$ngrok = $null
$currentStep = 'inicio'

try {
    $currentStep = 'eliminar public/hot'
    Write-Host "Paso: $currentStep"
    if ([IO.File]::Exists($hotPath)) {
        [IO.File]::Delete($hotPath)
    }

    $currentStep = 'limpiar cache de Laravel'
    Write-Host "Paso: $currentStep"
    & php artisan optimize:clear
    Assert-LastExitCode 'php artisan optimize:clear'

    $currentStep = 'compilar frontend'
    Write-Host "Paso: $currentStep"
    & npm.cmd run build
    Assert-LastExitCode 'npm run build'

    $currentStep = 'validar manifest y assets locales'
    Write-Host "Paso: $currentStep"
    if (-not [IO.File]::Exists($manifestPath)) {
        throw 'No existe public/build/manifest.json.'
    }

    $manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
    foreach ($entry in $manifest.PSObject.Properties.Value) {
        $entryAssets = @()
        foreach ($propertyName in @('file', 'css', 'assets')) {
            $property = $entry.PSObject.Properties[$propertyName]
            if ($property) {
                $entryAssets += @($property.Value)
            }
        }

        foreach ($asset in $entryAssets) {
            if ($asset -and -not [IO.File]::Exists((Join-Path (Split-Path $manifestPath) $asset))) {
                throw "Falta el asset declarado en el manifest: $asset"
            }
        }
    }

    if ([IO.File]::Exists($hotPath)) {
        throw 'public/hot reaparecio despues del build.'
    }

    $currentStep = 'iniciar Laravel'
    Write-Host "Paso: $currentStep"
    $laravel = Start-Process -FilePath (Get-Command php).Source `
        -ArgumentList @('artisan', 'serve', '--host=127.0.0.1', '--port=8000') `
        -WorkingDirectory $projectRoot -PassThru -WindowStyle Hidden `
        -RedirectStandardOutput $laravelOut -RedirectStandardError $laravelErr

    $localReady = $false
    $currentStep = 'esperar Laravel (maximo 30 segundos)'
    Write-Host "Paso: $currentStep"
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        Start-Sleep -Milliseconds 500
        try {
            $response = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:8000/login' -TimeoutSec 3
            if ($response.StatusCode -eq 200) {
                $localReady = $true
                break
            }
        } catch {}
    }
    if (-not $localReady) {
        throw 'Laravel no respondio /login en el puerto 8000.'
    }

    $currentStep = 'iniciar ngrok'
    Write-Host "Paso: $currentStep"
    $ngrok = Start-Process -FilePath (Get-Command ngrok).Source `
        -ArgumentList @('http', '8000') -PassThru -WindowStyle Hidden `
        -RedirectStandardOutput $ngrokOut -RedirectStandardError $ngrokErr

    $tunnel = $null
    $currentStep = 'esperar ngrok (maximo 30 segundos)'
    Write-Host "Paso: $currentStep"
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        Start-Sleep -Milliseconds 500
        try {
            $tunnels = Invoke-RestMethod -Uri 'http://127.0.0.1:4040/api/tunnels' -TimeoutSec 3
            $tunnel = @($tunnels.tunnels | Where-Object { $_.proto -eq 'https' -and $_.config.addr -match '(^|:)8000$' }) | Select-Object -First 1
            if ($tunnel) {
                break
            }
        } catch {}
    }
    if (-not $tunnel) {
        throw 'ngrok no publico un tunel HTTPS hacia el puerto 8000.'
    }

    $currentStep = 'validar assets publicos HTTPS'
    Write-Host "Paso: $currentStep"
    & powershell -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'diagnose-ngrok-preview.ps1') -PublicUrl $tunnel.public_url
    Assert-LastExitCode 'diagnose-ngrok-preview.ps1'

    $currentStep = 'validar redireccion publica'
    Write-Host "Paso: $currentStep"
    Add-Type -AssemblyName System.Net.Http
    $handler = New-Object System.Net.Http.HttpClientHandler
    $handler.AllowAutoRedirect = $false
    $client = New-Object System.Net.Http.HttpClient($handler)
    $client.DefaultRequestHeaders.Add('ngrok-skip-browser-warning', 'true')
    try {
        $rootResponse = $client.GetAsync("$($tunnel.public_url)/").GetAwaiter().GetResult()
        $expectedLocation = "$($tunnel.public_url)/login"
        if ([int] $rootResponse.StatusCode -ne 302 -or $rootResponse.Headers.Location.AbsoluteUri -ne $expectedLocation) {
            throw "La redireccion publica no coincide con $expectedLocation."
        }
    } finally {
        $client.Dispose()
        $handler.Dispose()
    }

    $laravelListener = Get-NetTCPConnection -State Listen -LocalPort 8000
    $ngrokListener = Get-NetTCPConnection -State Listen -LocalPort 4040

    Write-Host ''
    Write-Host "URL publica: $($tunnel.public_url)"
    Write-Host "Laravel PID: $($laravel.Id) (listener PID: $($laravelListener.OwningProcess))"
    Write-Host "ngrok PID: $($ngrokListener.OwningProcess)"
    Write-Host 'Laravel y ngrok quedan ejecutandose.'
} catch {
    [Console]::Error.WriteLine("Fallo en el paso '$currentStep': $($_.Exception.Message)")
    try { Stop-StudioLemusServer } catch {}
    try { Stop-NgrokTunnel } catch {}
    throw "Inicio de vista previa cancelado en '$currentStep'."
} finally {
    Pop-Location
}

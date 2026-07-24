param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^https://')]
    [string] $PublicUrl
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

Add-Type -AssemblyName System.Net.Http

$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$publicPath = Join-Path $projectRoot 'public'
$baseUri = [Uri]($PublicUrl.TrimEnd('/') + '/')
$handler = New-Object System.Net.Http.HttpClientHandler
$handler.AllowAutoRedirect = $false
$handler.AutomaticDecompression = [System.Net.DecompressionMethods]::GZip -bor [System.Net.DecompressionMethods]::Deflate
$client = New-Object System.Net.Http.HttpClient($handler)
$client.DefaultRequestHeaders.Add('ngrok-skip-browser-warning', 'true')
$client.DefaultRequestHeaders.Add('Accept-Encoding', 'gzip, deflate')
$sha256 = [System.Security.Cryptography.SHA256]::Create()

function Get-Response([Uri] $Uri) {
    $response = $client.GetAsync($Uri).GetAwaiter().GetResult()
    $bytes = $response.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult()

    return [pscustomobject]@{
        Uri = $Uri
        Status = [int] $response.StatusCode
        ContentType = if ($response.Content.Headers.ContentType) { $response.Content.Headers.ContentType.MediaType } else { '' }
        Bytes = $bytes
    }
}

function Get-Sha256([byte[]] $Bytes) {
    return ([BitConverter]::ToString($sha256.ComputeHash($Bytes))).Replace('-', '').ToLowerInvariant()
}

function Add-Asset([System.Collections.Generic.HashSet[string]] $Assets, [string] $Reference, [Uri] $ParentUri) {
    if ([string]::IsNullOrWhiteSpace($Reference)) {
        return
    }

    $assetUri = New-Object Uri($ParentUri, $Reference)
    if ($assetUri.AbsolutePath -like '/build/assets/*') {
        [void] $Assets.Add($assetUri.AbsoluteUri)
    }
}

try {
    $loginUri = New-Object Uri($baseUri, 'login')
    $login = Get-Response $loginUri
    if ($login.Status -ne 200) {
        throw "GET /login devolvio $($login.Status), se esperaba 200."
    }

    $html = [Text.Encoding]::UTF8.GetString($login.Bytes)
    if ($html -match '127\.0\.0\.1:5173|@vite/client') {
        throw 'El HTML todavia referencia el servidor de desarrollo de Vite.'
    }
    if ($html -match 'http://[^\s"'']+/build/assets/') {
        throw 'El HTML contiene assets HTTP y produciria Mixed Content.'
    }

    $assets = New-Object 'System.Collections.Generic.HashSet[string]'
    foreach ($match in [regex]::Matches($html, '(?:href|src)=["'']([^"'']+/build/assets/[^"'']+)["'']', 'IgnoreCase')) {
        Add-Asset $assets $match.Groups[1].Value $loginUri
    }
    if ($assets.Count -eq 0) {
        throw 'No se encontraron href o src de /build/assets/ en /login.'
    }

    $results = New-Object System.Collections.Generic.List[object]
    $pending = New-Object System.Collections.Generic.Queue[string]
    foreach ($asset in $assets) {
        $pending.Enqueue($asset)
    }

    while ($pending.Count -gt 0) {
        $assetUrl = $pending.Dequeue()
        $assetUri = [Uri] $assetUrl
        $response = Get-Response $assetUri
        $extension = [IO.Path]::GetExtension($assetUri.AbsolutePath).ToLowerInvariant()
        $localRelativePath = [Uri]::UnescapeDataString($assetUri.AbsolutePath.TrimStart('/')).Replace('/', [IO.Path]::DirectorySeparatorChar)
        $localPath = Join-Path $publicPath $localRelativePath

        if ($response.Status -ne 200) {
            throw "$assetUrl devolvio HTTP $($response.Status)."
        }
        if ($response.Bytes.Length -eq 0) {
            throw "$assetUrl no tiene contenido."
        }
        if ($response.ContentType -eq 'text/html') {
            throw "$assetUrl devolvio HTML en lugar del asset solicitado."
        }
        if ($extension -eq '.js' -and $response.ContentType -notin @('text/javascript', 'application/javascript', 'application/x-javascript')) {
            throw "$assetUrl tiene Content-Type JavaScript invalido: $($response.ContentType)."
        }
        if ($extension -eq '.css' -and $response.ContentType -ne 'text/css') {
            throw "$assetUrl tiene Content-Type CSS invalido: $($response.ContentType)."
        }
        if (-not [IO.File]::Exists($localPath)) {
            throw "El asset publico no existe localmente: $localPath"
        }

        $publicHash = Get-Sha256 $response.Bytes
        $localHash = (Get-FileHash -LiteralPath $localPath -Algorithm SHA256).Hash.ToLowerInvariant()
        if ($publicHash -ne $localHash) {
            throw "El hash publico no coincide con el archivo local: $assetUrl"
        }

        $results.Add([pscustomobject]@{
            URL = $assetUrl
            Status = $response.Status
            ContentType = $response.ContentType
            Bytes = $response.Bytes.Length
            SHA256 = $publicHash
        })

        if ($extension -eq '.css') {
            $css = [Text.Encoding]::UTF8.GetString($response.Bytes)
            foreach ($match in [regex]::Matches($css, 'url\(["'']?([^\)"'']+)["'']?\)', 'IgnoreCase')) {
                $beforeCount = $assets.Count
                Add-Asset $assets $match.Groups[1].Value $assetUri
                if ($assets.Count -gt $beforeCount) {
                    $resolved = (New-Object Uri($assetUri, $match.Groups[1].Value)).AbsoluteUri
                    $pending.Enqueue($resolved)
                }
            }
        }
    }

    Write-Host "GET $loginUri -> 200 ($($login.ContentType), $($login.Bytes.Length) bytes)"
    foreach ($result in ($results | Sort-Object URL)) {
        Write-Host "Asset: $($result.URL)"
        Write-Host "  Status: $($result.Status)"
        Write-Host "  Content-Type: $($result.ContentType)"
        Write-Host "  Bytes: $($result.Bytes)"
        Write-Host "  SHA256: $($result.SHA256)"
    }
    Write-Host "Diagnostico correcto: $($results.Count) assets coinciden en estado, MIME, tamano y hash con public/build."
} finally {
    $sha256.Dispose()
    $client.Dispose()
    $handler.Dispose()
}

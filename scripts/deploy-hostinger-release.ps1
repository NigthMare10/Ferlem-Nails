param(
    [string]$HostName = '212.1.208.248',
    [int]$Port = 65002,
    [string]$UserName = 'u357586881',
    [string]$AppRoot = '/home/u357586881/domains/violet-crow-104407.hostingersite.com/studio-lemus',
    [string]$PublicRoot = '/home/u357586881/domains/violet-crow-104407.hostingersite.com/public_html',
    [Parameter(Mandatory)]
    [string]$BaseUrl,
    [securestring]$Password
)

$ErrorActionPreference = 'Stop'
$BaseUrl = $BaseUrl.TrimEnd('/')
$projectRoot = Split-Path -Parent $PSScriptRoot
$releaseBuilder = Join-Path $PSScriptRoot 'build-hostinger-release.ps1'
$zipPath = Join-Path $projectRoot 'deploy\hostinger\studio-lemus-production.zip'
$releaseId = "release-$(Get-Date -Format 'yyyyMMddHHmmss')"

function Invoke-Step {
    param([string]$Name, [scriptblock]$Action)

    Write-Host "[deploy] $Name"
    & $Action
    if ($LASTEXITCODE -ne 0) {
        throw "Falló: $Name (exit code $LASTEXITCODE)."
    }
}

if (-not $Password) {
    $Password = Read-Host 'Contraseña SSH' -AsSecureString
}

$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($Password)
try {
    $plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
}

$askPass = Join-Path ([System.IO.Path]::GetTempPath()) ("studio-lemus-askpass-$([guid]::NewGuid()).cmd")
[System.IO.File]::WriteAllText($askPass, "@echo %STUDIO_DEPLOY_PASSWORD%`r`n", [System.Text.Encoding]::ASCII)
$env:SSH_ASKPASS = $askPass
$env:SSH_ASKPASS_REQUIRE = 'force'
$env:DISPLAY = 'studio-lemus'
$env:STUDIO_DEPLOY_PASSWORD = $plainPassword
$sshOptions = @('-o', 'BatchMode=no', '-o', 'ConnectTimeout=20', '-o', 'ServerAliveInterval=15', '-o', 'ServerAliveCountMax=4')

try {
    Invoke-Step 'Ejecutando typecheck local' { & npm.cmd run typecheck }
    Invoke-Step 'Compilando assets locales' { & npm.cmd run build }

    if (Test-Path -LiteralPath (Join-Path $projectRoot 'public\hot')) {
        throw 'public/hot existe. Detenga Vite y elimine ese marcador antes de desplegar.'
    }
    if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'vendor\autoload.php') -PathType Leaf)) {
        throw 'Falta vendor/autoload.php; no se puede crear un release de producción.'
    }
    if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'public\build\manifest.json') -PathType Leaf)) {
        throw 'Falta public/build/manifest.json después del build.'
    }

    Invoke-Step 'Preparando release local' { & $releaseBuilder -ProjectRoot $projectRoot }
    if (-not (Test-Path -LiteralPath $zipPath -PathType Leaf)) {
        throw 'El ZIP de release no fue creado.'
    }

    $remoteScript = @'
set -Eeuo pipefail

APP_ROOT="$DEPLOY_APP_ROOT"
PUBLIC_ROOT="$DEPLOY_PUBLIC_ROOT"
BASE_URL="${DEPLOY_BASE_URL%/}"
RELEASE_ID="$DEPLOY_RELEASE_ID"
PHP_BIN="${PHP_BIN:-php}"
STAGING_BASE="$APP_ROOT/.deploy-staging"
BACKUP_BASE="$APP_ROOT/.deploy-backups"
STAGE="$STAGING_BASE/$RELEASE_ID"
BACKUP="$BACKUP_BASE/$RELEASE_ID"
ZIP="$STAGE/release.zip"
PUBLISHED=0
MAINTENANCE=0
STEP='inicialización'
APP_PATHS='app bootstrap config database resources routes vendor public artisan composer.json composer.lock'

fail() { echo "[deploy] Falló en: $STEP" >&2; exit 1; }
rollback() {
    echo '[deploy] Restaurando respaldo por fallo.' >&2
    if [ -d "$BACKUP/app" ]; then
        for path in $APP_PATHS; do rm -rf "$APP_ROOT/$path"; [ -e "$BACKUP/app/$path" ] && cp -a "$BACKUP/app/$path" "$APP_ROOT/$path"; done
    fi
    if [ -f "$BACKUP/public-items" ]; then
        while IFS= read -r item; do
            [ -n "$item" ] || continue
            rm -rf "$PUBLIC_ROOT/$item"
            [ -e "$BACKUP/public_html/$item" ] && cp -a "$BACKUP/public_html/$item" "$PUBLIC_ROOT/$item"
        done < "$BACKUP/public-items"
    fi
    [ -f "$BACKUP/public_html/hot" ] && cp -a "$BACKUP/public_html/hot" "$PUBLIC_ROOT/hot" || rm -f "$PUBLIC_ROOT/hot"
    if [ -f "$APP_ROOT/artisan" ]; then "$PHP_BIN" "$APP_ROOT/artisan" up >/dev/null 2>&1 || true; fi
}
finish() {
    status=$?
    if [ "$status" -ne 0 ] && [ "$PUBLISHED" -eq 1 ]; then rollback; fi
    if [ "$MAINTENANCE" -eq 1 ] && [ -f "$APP_ROOT/artisan" ]; then "$PHP_BIN" "$APP_ROOT/artisan" up >/dev/null 2>&1 || true; fi
    rm -rf "$STAGE"
    exit "$status"
}
trap fail ERR
trap finish EXIT

[ -f "$APP_ROOT/artisan" ] || { echo "No existe artisan en $APP_ROOT." >&2; exit 1; }
[ -f "$APP_ROOT/.env" ] || { echo "No existe .env en $APP_ROOT." >&2; exit 1; }
[ -f "$PUBLIC_ROOT/index.php" ] || { echo "No existe index.php en $PUBLIC_ROOT." >&2; exit 1; }
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'
command -v unzip >/dev/null
command -v curl >/dev/null

STEP='preparar staging remoto'
mkdir -p "$STAGE" "$BACKUP_BASE"
chmod 700 "$STAGING_BASE" "$BACKUP_BASE"
[ -f "$ZIP" ] || { echo 'No se recibió el ZIP de release.' >&2; exit 1; }
unzip -q "$ZIP" -d "$STAGE/release"
for path in $APP_PATHS; do [ -e "$STAGE/release/$path" ] || { echo "El release no contiene $path." >&2; exit 1; }; done
[ ! -e "$STAGE/release/public/hot" ] || { echo 'El release contiene public/hot.' >&2; exit 1; }

STEP='validar release temporal con PHP 8.3'
cp "$APP_ROOT/.env" "$STAGE/release/.env"
chmod 600 "$STAGE/release/.env"
rm -rf "$STAGE/release/storage"
ln -s "$APP_ROOT/storage" "$STAGE/release/storage"
mkdir -p "$STAGE/release/bootstrap/cache"
chmod 775 "$STAGE/release/bootstrap/cache"
"$PHP_BIN" "$STAGE/release/artisan" migrate:status --no-interaction >/dev/null
test -f "$STAGE/release/vendor/autoload.php"
test -f "$STAGE/release/public/build/manifest.json"

STEP='crear respaldo de la versión actual'
mkdir -p "$BACKUP/app" "$BACKUP/public_html"
for path in $APP_PATHS; do [ -e "$APP_ROOT/$path" ] && cp -a "$APP_ROOT/$path" "$BACKUP/app/$path"; done
find "$STAGE/release/public" -mindepth 1 -maxdepth 1 ! -name index.php ! -name storage -printf '%f\n' > "$BACKUP/public-items"
while IFS= read -r item; do [ -n "$item" ] && [ -e "$PUBLIC_ROOT/$item" ] && cp -a "$PUBLIC_ROOT/$item" "$BACKUP/public_html/$item"; done < "$BACKUP/public-items"
[ -f "$PUBLIC_ROOT/hot" ] && cp -a "$PUBLIC_ROOT/hot" "$BACKUP/public_html/hot" || true

STEP='activar mantenimiento'
"$PHP_BIN" "$APP_ROOT/artisan" down --retry=60
MAINTENANCE=1

STEP='publicar release'
for path in $APP_PATHS; do rm -rf "$APP_ROOT/$path"; mv "$STAGE/release/$path" "$APP_ROOT/$path"; done
while IFS= read -r item; do [ -n "$item" ] && rm -rf "$PUBLIC_ROOT/$item" && cp -a "$APP_ROOT/public/$item" "$PUBLIC_ROOT/$item"; done < "$BACKUP/public-items"
PUBLISHED=1

STEP='preparar permisos y storage persistente'
mkdir -p "$APP_ROOT/storage/framework/cache/data" "$APP_ROOT/storage/framework/sessions" "$APP_ROOT/storage/framework/views" "$APP_ROOT/storage/logs" "$APP_ROOT/bootstrap/cache"
chmod 775 "$APP_ROOT/storage" "$APP_ROOT/storage/framework" "$APP_ROOT/storage/framework/cache" "$APP_ROOT/storage/framework/cache/data" "$APP_ROOT/storage/framework/sessions" "$APP_ROOT/storage/framework/views" "$APP_ROOT/storage/logs" "$APP_ROOT/bootstrap/cache"
chmod 600 "$APP_ROOT/.env"
[ -e "$PUBLIC_ROOT/storage" ] || ln -s ../studio-lemus/storage/app/public "$PUBLIC_ROOT/storage"
test -e "$PUBLIC_ROOT/storage"
test ! -e "$APP_ROOT/public/hot"
test ! -e "$PUBLIC_ROOT/hot"

STEP='migrar y regenerar cachés'
cd "$APP_ROOT"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

STEP='salir de mantenimiento'
"$PHP_BIN" artisan up
MAINTENANCE=0

STEP='smoke test público final'
manifest_assets() {
    "$PHP_BIN" -r '$manifest=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); foreach ($manifest as $entry) { if (isset($entry["file"])) echo $entry["file"], PHP_EOL; foreach (($entry["css"] ?? []) as $css) echo $css, PHP_EOL; }' "$PUBLIC_ROOT/build/manifest.json" | sort -u
}
assert_no_trace() { ! grep -Eiq 'stack trace|vendor/laravel/framework|Whoops|Ignition|Fatal error|APP_DEBUG' "$1"; }
request_status() {
    name="$1"
    url="$2"
    expected="$3"
    status="$(curl --silent --show-error --output "$STAGE/$name.html" --write-out '%{http_code}' --connect-timeout 15 --max-time 30 "$url" || true)"
    echo "[smoke] $name: $status"
    [ "$status" = "$expected" ] || { echo "[smoke] Respuesta inesperada de $url:" >&2; sed -n '1,80p' "$STAGE/$name.html" >&2 || true; exit 1; }
}
for attempt in 1 2 3; do
    root_status="$(curl --silent --show-error --output "$STAGE/root.html" --write-out '%{http_code}' --connect-timeout 15 --max-time 30 "$BASE_URL/" || true)"
    echo "[smoke] /: $root_status"
    [ "$root_status" = 200 ] || [ "$root_status" = 302 ] || { echo "GET / devolvió $root_status." >&2; exit 1; }
    request_status login "$BASE_URL/login" 200
    grep -Eiq 'Studio Lemus' "$STAGE/login.html"
    grep -Eiq '<form|iniciar sesión|iniciar sesion|login' "$STAGE/login.html"
    assert_no_trace "$STAGE/login.html"
    request_status up "$BASE_URL/up" 200
    request_status expenses "$BASE_URL/expenses" 302
    request_status earnings "$BASE_URL/earnings" 302
    manifest_assets > "$STAGE/assets.txt"
    while IFS= read -r asset; do request_status asset "$BASE_URL/build/$asset" 200; done < "$STAGE/assets.txt"
    sleep 2
done
test -f "$PUBLIC_ROOT/build/manifest.json"
test ! -e "$PUBLIC_ROOT/hot"
"$PHP_BIN" artisan migrate:status --no-interaction >/dev/null
test -w storage/framework/cache/data
test -w storage/framework/sessions
test -w storage/framework/views
test -w storage/logs
test -w bootstrap/cache
env_status="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --connect-timeout 15 --max-time 30 "$BASE_URL/.env" || true)"
[ "$env_status" = 403 ] || [ "$env_status" = 404 ] || { echo ".env devolvió $env_status." >&2; exit 1; }
echo "[deploy] Despliegue y smoke test correctos: $BASE_URL"
'@

    $payload = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($remoteScript))
    $remoteTarget = "${UserName}@${HostName}"
    $remoteStage = "$AppRoot/.deploy-staging/$releaseId"

    Invoke-Step 'Creando carpeta temporal remota' { & ssh.exe @sshOptions -p $Port $remoteTarget "mkdir -p '$remoteStage' && chmod 700 '$remoteStage'" }
    Invoke-Step 'Subiendo release temporal' { & scp.exe @sshOptions -P $Port $zipPath "${remoteTarget}:$remoteStage/release.zip" }
    Invoke-Step 'Publicando y validando release remoto' {
        $environment = "export DEPLOY_APP_ROOT='$AppRoot' DEPLOY_PUBLIC_ROOT='$PublicRoot' DEPLOY_BASE_URL='$BaseUrl' DEPLOY_RELEASE_ID='$releaseId';"
        & ssh.exe @sshOptions -p $Port $remoteTarget "$environment echo '$payload' | base64 -d | timeout 600 bash"
    }
} catch {
    Write-Error $_.Exception.Message
    exit 1
} finally {
    Remove-Item -LiteralPath $askPass -Force -ErrorAction SilentlyContinue
    Remove-Item Env:SSH_ASKPASS -ErrorAction SilentlyContinue
    Remove-Item Env:SSH_ASKPASS_REQUIRE -ErrorAction SilentlyContinue
    Remove-Item Env:STUDIO_DEPLOY_PASSWORD -ErrorAction SilentlyContinue
    $plainPassword = $null
}

#!/usr/bin/env bash
set -Eeuo pipefail

BASE_URL=${1:-${DEPLOY_BASE_URL:-}}
BASE_URL=${BASE_URL%/}
PUBLIC_ROOT=${PUBLIC_ROOT:-public}
export PUBLIC_ROOT

log() {
    printf '[smoke] %s\n' "$*"
}

fail() {
    log "ERROR: $*"
    exit 1
}

command -v curl >/dev/null 2>&1 || fail "curl no esta disponible."
command -v php >/dev/null 2>&1 || fail "php no esta disponible."
[ -n "$BASE_URL" ] || fail "Indique la URL HTTPS como primer argumento."
case "$BASE_URL" in
    https://*) ;;
    *) fail "La URL debe comenzar con https://." ;;
esac
[ -f artisan ] || fail "Ejecute el script desde la raiz de Laravel."
[ -f "$PUBLIC_ROOT/build/manifest.json" ] || fail "Falta $PUBLIC_ROOT/build/manifest.json."

tmp_dir=$(mktemp -d)
trap 'rm -rf "$tmp_dir"' EXIT HUP INT TERM

curl_https() {
    curl --fail --silent --show-error --location \
        --proto '=https' --proto-redir '=https' \
        --connect-timeout 10 --max-time 30 "$@"
}

assert_no_trace() {
    if grep -Eiq 'stack trace|vendor/laravel/framework|Whoops|Ignition|Fatal error|APP_DEBUG' "$1"; then
        fail "Se encontro una traza o pagina de depuracion en $2."
    fi
}

log "Comprobando redireccion HTTP a HTTPS."
effective_url=$(curl --silent --show-error --location --output /dev/null \
    --connect-timeout 10 --max-time 30 --write-out '%{url_effective}' \
    "http://${BASE_URL#https://}")
case "$effective_url" in
    https://*) ;;
    *) fail "HTTP no termina redirigiendo a HTTPS." ;;
esac

log "Comprobando login y ausencia de trazas."
curl_https --output "$tmp_dir/login.html" "$BASE_URL/login"
grep -Eiq '<form|login|iniciar' "$tmp_dir/login.html" || fail "La respuesta no parece ser el login."
assert_no_trace "$tmp_dir/login.html" "/login"

log "Comprobando health endpoint."
curl_https --output "$tmp_dir/up.txt" "$BASE_URL/up"
assert_no_trace "$tmp_dir/up.txt" "/up"

log "Comprobando assets publicados por el manifest."
php -r '
$manifest = json_decode(file_get_contents(getenv("PUBLIC_ROOT")."/build/manifest.json"), true, 512, JSON_THROW_ON_ERROR);
$files = [];
foreach ($manifest as $entry) {
    if (isset($entry["file"])) { $files[] = $entry["file"]; }
    foreach ($entry["css"] ?? [] as $css) { $files[] = $css; }
}
foreach (array_unique($files) as $file) {
    if (! preg_match("#^[A-Za-z0-9_./-]+$#", $file)) { exit(2); }
    echo $file, PHP_EOL;
}
' > "$tmp_dir/assets.txt"
[ -s "$tmp_dir/assets.txt" ] || fail "El manifest no contiene assets."
while IFS= read -r asset; do
    curl_https --output /dev/null "$BASE_URL/build/$asset"
done < "$tmp_dir/assets.txt"

log "Comprobando conexion Artisan y estado de migraciones."
php artisan migrate:status --no-interaction

log "Comprobando storage sin escribir datos."
[ -d storage/framework ] || fail "Falta storage/framework."
[ -d storage/logs ] || fail "Falta storage/logs."
[ -r storage ] && [ -x storage ] || fail "storage no es accesible."
[ -w storage/framework ] || fail "storage/framework no es escribible por PHP CLI."
[ -w bootstrap/cache ] || fail "bootstrap/cache no es escribible por PHP CLI."
[ -e "$PUBLIC_ROOT/storage" ] || fail "Falta $PUBLIC_ROOT/storage."

log "Smoke test de solo lectura completado."

#!/usr/bin/env bash
set -Eeuo pipefail

umask 077

APP_ROOT=${APP_ROOT:-$(pwd)}
DEPLOY_REF=${DEPLOY_REF:-${1:-}}
DEPLOY_BASE_URL=${DEPLOY_BASE_URL:-${2:-}}
BACKUP_DIR=${BACKUP_DIR:-"$HOME/backups/studio-lemus"}
maintenance_started=0

log() {
    printf '[deploy] %s\n' "$*"
}

on_error() {
    local status=$?
    log "Fallo en la linea $1 (estado $status)."
    return "$status"
}

finish() {
    local status=$?

    if [ "$maintenance_started" -eq 1 ]; then
        log "Desactivando mantenimiento."
        php artisan up >/dev/null 2>&1 || true
    fi

    if [ "$status" -ne 0 ]; then
        log "Despliegue fallido; revise la salida y aplique rollback si corresponde."
    fi

    exit "$status"
}

trap 'on_error "$LINENO"' ERR
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM HUP

cd "$APP_ROOT"

command -v php >/dev/null 2>&1 || { log "php no esta disponible."; exit 1; }
command -v composer >/dev/null 2>&1 || { log "composer no esta disponible."; exit 1; }
[ -f artisan ] || { log "artisan no existe en APP_ROOT=$APP_ROOT."; exit 1; }
[ -f .env ] || { log "Falta el .env privado de produccion."; exit 1; }

[ -f vendor/autoload.php ] || {
    log "Falta vendor/autoload.php. Complete primero la instalacion inicial documentada."
    exit 1
}

log "Activando mantenimiento."
php artisan down --retry=60
maintenance_started=1

environment=$(php artisan env --no-ansi 2>&1)
case "$environment" in
    *production*) ;;
    *) log "APP_ENV no es production; se cancela el despliegue."; exit 1 ;;
esac

log "Validando que la conexion y la base no sean de testing."
php artisan tinker --execute='if (! in_array(config("database.default"), ["mysql", "mariadb"], true) || app()->environment("testing") || preg_match("/(^|[_-])test(ing)?($|[_-])/i", basename((string) config("database.connections.".config("database.default").".database")))) { exit(42); }' >/dev/null

if command -v mysqldump >/dev/null 2>&1; then
    [ -n "${MYSQL_DEFAULTS_FILE:-}" ] || {
        log "mysqldump esta disponible pero falta MYSQL_DEFAULTS_FILE; no se migrara sin backup."
        exit 1
    }
    [ -r "$MYSQL_DEFAULTS_FILE" ] || {
        log "MYSQL_DEFAULTS_FILE no existe o no es legible."
        exit 1
    }
    [ -n "${DEPLOY_DB_NAME:-}" ] || {
        log "mysqldump esta disponible pero falta DEPLOY_DB_NAME."
        exit 1
    }
    case "$DEPLOY_DB_NAME" in
        *test*|*Test*|*TEST*) log "DEPLOY_DB_NAME parece una base de testing."; exit 1 ;;
    esac

    mkdir -p "$BACKUP_DIR"
    chmod 700 "$BACKUP_DIR"
    backup_file="$BACKUP_DIR/pre-deploy-$(date -u +%Y%m%dT%H%M%SZ).sql"
    log "Creando backup previo en $backup_file"
    mysqldump \
        --defaults-extra-file="$MYSQL_DEFAULTS_FILE" \
        --single-transaction \
        --quick \
        --routines \
        --triggers \
        --default-character-set=utf8mb4 \
        "$DEPLOY_DB_NAME" > "$backup_file"
    chmod 600 "$backup_file"
else
    log "Aviso: mysqldump no esta disponible; verifique el backup de hPanel antes de continuar."
fi

if [ -n "$DEPLOY_REF" ]; then
    command -v git >/dev/null 2>&1 || { log "git no esta disponible para obtener DEPLOY_REF."; exit 1; }
    log "Obteniendo el ref aprobado: $DEPLOY_REF"
    git fetch --no-tags origin "$DEPLOY_REF"
    git checkout --detach FETCH_HEAD
fi

log "Instalando dependencias PHP de produccion."
composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-progress

[ -f public/build/manifest.json ] || {
    log "Falta public/build/manifest.json. Compile y suba los assets desde local."
    exit 1
}

log "Preparando storage y ejecutando migraciones."
php artisan storage:link --no-interaction
php artisan migrate --force --no-interaction

log "Regenerando caches de produccion."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

log "Aplicando permisos."
chmod 600 .env
find public -type d -exec chmod 755 {} \;
find public -type f -exec chmod 644 {} \;
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
chmod 755 artisan scripts/deploy-hostinger.sh scripts/smoke-test-production.sh

php artisan up
maintenance_started=0

if [ -n "$DEPLOY_BASE_URL" ]; then
    log "Ejecutando smoke test."
    bash scripts/smoke-test-production.sh "$DEPLOY_BASE_URL"
else
    log "DEPLOY_BASE_URL no esta definido; smoke test omitido."
fi

log "Despliegue completado."

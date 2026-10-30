#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
APP=/home/u357586881/domains/steelblue-opossum-594306.hostingersite.com/studio-lemus
PUBLIC=/home/u357586881/domains/steelblue-opossum-594306.hostingersite.com/public_html
STAGE="$1"
case "$STAGE" in /home/u357586881/lemus-releases/release-*) ;; *) echo 'Invalid release path'; exit 1;; esac
BACKUP="$STAGE/backup"
RELEASE="$STAGE/source"
PATHS='app bootstrap config database resources routes vendor public artisan composer.json composer.lock'
published=0
maintenance=0
finish() {
    status=$?
    if [ "$status" -ne 0 ] && [ "$published" -eq 1 ]; then
        echo 'Restoring previous application files; additive database migrations remain recorded.'
        for path in $PATHS; do
            if [ -e "$BACKUP/live/$path" ]; then
                [ ! -e "$APP/$path" ] || mv "$APP/$path" "$STAGE/failed-${path//\//-}"
                mv "$BACKUP/live/$path" "$APP/$path"
            fi
        done
        if [ -d "$BACKUP/public-build" ]; then
            [ ! -d "$PUBLIC/build" ] || mv "$PUBLIC/build" "$STAGE/failed-public-build"
            mv "$BACKUP/public-build" "$PUBLIC/build"
        fi
        (cd "$APP" && php artisan config:clear && php artisan route:clear) || true
    fi
    if [ "$maintenance" -eq 1 ]; then (cd "$APP" && php artisan up) || true; fi
    exit "$status"
}
trap finish EXIT
test -f "$RELEASE/vendor/autoload.php"
test -f "$RELEASE/public/build/manifest.json"
test ! -f "$RELEASE/.env"
test ! -e "$RELEASE/public/hot"
mkdir -p "$BACKUP/live"
chmod 700 "$STAGE" "$BACKUP"
cd "$APP"
php artisan down --retry=60
maintenance=1
php "$STAGE/metadata.php" "$APP" "$BACKUP"
mysqldump --defaults-extra-file="$BACKUP/mysql.cnf" --single-transaction --quick --no-tablespaces "$(cat "$BACKUP/database-name")" > "$BACKUP/database.sql"
test -s "$BACKUP/database.sql"
gzip "$BACKUP/database.sql"
gzip -t "$BACKUP/database.sql.gz"
tar -czf "$BACKUP/application-and-storage.tar.gz" $PATHS storage .env
tar -tzf "$BACKUP/application-and-storage.tar.gz" >/dev/null
sha256sum "$BACKUP/database.sql.gz" "$BACKUP/application-and-storage.tar.gz" > "$BACKUP/SHA256SUMS"
echo 'Verified private database and application/storage backups.'
cp "$APP/.env" "$RELEASE/.env"
ln -s "$APP/storage" "$RELEASE/storage"
mkdir -p "$RELEASE/bootstrap/cache"
(cd "$RELEASE" && php artisan package:discover --no-ansi && php artisan migrate:status --no-ansi >/dev/null)
published=1
for path in $PATHS; do
    [ ! -e "$APP/$path" ] || mv "$APP/$path" "$BACKUP/live/$path"
    mv "$RELEASE/$path" "$APP/$path"
done
mkdir -p "$APP/bootstrap/cache"
chmod 775 "$APP/bootstrap/cache"
php artisan optimize:clear
php artisan migrate --force --no-interaction
php artisan permission:cache-reset
php artisan config:cache
php artisan route:cache
php artisan view:cache
php "$STAGE/metadata.php" "$APP" counts > "$BACKUP/after-counts.json"
cmp "$BACKUP/before-counts.json" "$BACKUP/after-counts.json"
echo 'Existing business row counts unchanged.'
[ ! -d "$PUBLIC/build" ] || mv "$PUBLIC/build" "$BACKUP/public-build"
cp -a "$APP/public/build" "$PUBLIC/build"
find "$PUBLIC/build" -type d -exec chmod 755 {} \;
find "$PUBLIC/build" -type f -exec chmod 644 {} \;
chmod 600 "$APP/.env"
php artisan up
maintenance=0
for route in login clientes/login clientes/registro up; do
    code=$(curl -sS --max-time 30 -o "$STAGE/smoke-${route//\//-}.html" -w '%{http_code}' "https://steelblue-opossum-594306.hostingersite.com/$route")
    test "$code" = 200
    echo "Smoke /$route: $code"
done
echo "Release deployed. Private backup: $BACKUP"

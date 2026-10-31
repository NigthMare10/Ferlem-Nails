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
ASSET_PATHS='build fonts/stitch images/stitch'
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
        for asset in $ASSET_PATHS; do
            if [ -f "$BACKUP/asset-started-${asset//\//-}" ]; then
                [ ! -e "$PUBLIC/$asset" ] || mv "$PUBLIC/$asset" "$STAGE/failed-public-${asset//\//-}"
                [ ! -e "$BACKUP/public-assets/$asset" ] || mv "$BACKUP/public-assets/$asset" "$PUBLIC/$asset"
            fi
        done
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
php "$STAGE/metadata.php" "$APP" counts "$BACKUP/before-counts.json" > "$BACKUP/after-counts.json"
cmp "$BACKUP/before-counts.json" "$BACKUP/after-counts.json"
echo 'Existing business row counts unchanged.'
for asset in $ASSET_PATHS; do
    test -d "$APP/public/$asset"
    mkdir -p "$(dirname "$BACKUP/public-assets/$asset")" "$(dirname "$PUBLIC/$asset")"
    chmod 755 "$(dirname "$PUBLIC/$asset")"
    touch "$BACKUP/asset-started-${asset//\//-}"
    [ ! -e "$PUBLIC/$asset" ] || mv "$PUBLIC/$asset" "$BACKUP/public-assets/$asset"
    cp -a "$APP/public/$asset" "$PUBLIC/$asset"
    find "$PUBLIC/$asset" -type d -exec chmod 755 {} \;
    find "$PUBLIC/$asset" -type f -exec chmod 644 {} \;
done
chmod 600 "$APP/.env"
php artisan up
maintenance=0
for route in login clientes/login clientes/registro up fonts/stitch/font-0.woff2 images/stitch/home-0.webp; do
    code=$(curl -sS --max-time 30 -o "$STAGE/smoke-${route//\//-}.html" -w '%{http_code}' "https://steelblue-opossum-594306.hostingersite.com/$route")
    test "$code" = 200
    echo "Smoke /$route: $code"
done
echo "Release deployed. Private backup: $BACKUP"

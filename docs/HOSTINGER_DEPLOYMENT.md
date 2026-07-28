powershell -ExecutionPolicy Bypass -File .\scripts\deploy-hostinger-release.ps1 -BaseUrl "https://violet-crow-104407.hostingersite.com"


# Despliegue en Hostinger

Esta guia despliega Studio Lemus (Laravel 13, PHP 8.3 y MySQL) sin instalar Node.js en Hostinger. Los assets se compilan localmente y se publica `public/build` junto con el codigo.

> La estabilizacion A-D agrega una migracion aditiva de anulacion de ventas. Antes de cualquier despliegue aprobado, respalde MySQL, confirme PHP CLI 8.3, ejecute `php artisan migrate --force` una sola vez y reconcilie que ventas, lineas, pagos, citas y adelantos existentes no cambiaron de cantidad. Nunca use `migrate:fresh` ni `migrate:rollback` a ciegas cuando existan ventas anuladas.

> No use el auto-deploy de hPanel para este proyecto si su PHP no ofrece `proc_open`. Composer/Symfony Process no puede completar la instalacion en ese entorno. No se modifica Composer ni el codigo para evitarlo: use SSH manual solo cuando el PHP CLI tenga `proc_open`, o el release precompilado local descrito abajo.

## Despliegue confirmado en Hostinger

El despliegue inicial se realizo mediante SSH como transporte y release precompilado como metodo de instalacion. PHP CLI 8.3 estaba disponible, pero `proc_open` estaba deshabilitado tanto en el PHP predeterminado como en el binario 8.3 alternativo; Composer remoto queda descartado.

- Aplicacion privada: `.../domains/[DOMINIO]/studio-lemus`.
- Directorio publico: `.../domains/[DOMINIO]/public_html`.
- Se copiaron solamente los contenidos de `public/` al directorio publico y se ajusto `index.php` hacia `../studio-lemus`.
- La base se detecto vacia y recibio `migrate --force`, seeders RBAC y el owner inicial autorizado.
- El auto-deploy de hPanel no se utilizo. Desactive su integracion Git desde la interfaz hPanel para evitar que vuelva a intentar Composer con `proc_open` deshabilitado.
- Para futuras versiones: genere el ZIP local, subalo por SSH/SFTP, active mantenimiento si ya existe una version publica, reemplace solo codigo privado y `public/`, conserve `.env` y `storage`, restaure las rutas de `public_html/index.php` hacia `../studio-lemus`, migre, cachee y ejecute el smoke test con `PUBLIC_ROOT=../public_html`.
- Despliegue de estabilizacion 2026-07-25: se aplico `2026_07_25_130000_add_cancellation_fields_to_sales_table` como batch 2, se regeneraron caches y el smoke test HTTPS paso. `php artisan storage:link` falla porque `exec()` esta deshabilitado en PHP CLI; crear o restaurar el enlace desde shell SSH con `ln -s ../studio-lemus/storage/app/public ../public_html/storage` antes del smoke test.

## 1. Requisitos y decisiones

- Plan Hostinger con PHP 8.3, extensiones `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo` y Composer 2.
- Dominio con SSL activo.
- Base MySQL y usuario creados desde hPanel. No anotar credenciales en el repositorio ni en comandos compartidos.
- SSH recomendado. Si el plan no ofrece SSH, preparar el artefacto localmente y subirlo con SFTP o el administrador de archivos.
- El document root debe apuntar a `<APP_ROOT>/public`, nunca a `<APP_ROOT>`.
- No ejecutar `npm`, `vite`, seeders ni `php artisan key:generate` en cada despliegue.

Use valores reales solo en el `.env` privado del servidor. `.env.production.example` contiene marcadores entre corchetes, no credenciales.

## 2. Compilar el artefacto local, sin Node en Hostinger

En una copia limpia del commit aprobado:

```bash
npm ci
npm run build
test -f public/build/manifest.json
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

`public/build` debe viajar en el artefacto. Si se usa Git y esa carpeta esta ignorada, subirla por SFTP o crear un archivo de entrega que la incluya; no ejecutar Node en el servidor. No subir `.env`, `node_modules`, pruebas, caches locales ni dumps SQL al directorio publico.

Cuando Composer no puede ejecutarse en Hostinger por `proc_open`, genere el release precompilado local despues de instalar dependencias de produccion:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/build-hostinger-release.ps1
```

El resultado es `deploy/hostinger/studio-lemus-production.zip`. Contiene solo `app`, `bootstrap`, `config`, `database`, `public`, `resources`, `routes`, `storage`, `vendor`, `artisan`, `composer.json` y `composer.lock`. Excluye `.env`, Git, Node, tests, docs, logs, dumps y diagnosticos. Extraigalo en la carpeta privada de Laravel; no ejecute Composer en hPanel ni en el servidor cuando use este metodo.

Para actualizaciones futuras con release precompilado: active mantenimiento con el PHP CLI disponible, respalde MySQL, reemplace codigo privado sin sobrescribir `.env` ni `storage`, sincronice el contenido de `public/`, ejecute `migrate --force`, regenere caches, desactive mantenimiento y ejecute el smoke test. Si SSH no esta disponible, haga estos pasos desde hPanel/phpMyAdmin solo cuando el panel ofrezca una alternativa segura; de lo contrario solicite SSH al proveedor.

## 3. Estructura recomendada

Ejemplo conceptual, ajuste las rutas al usuario real de Hostinger:

```text
$HOME/apps/studio-lemus/       # APP_ROOT, codigo Laravel completo
$HOME/backups/studio-lemus/    # fuera del sitio web
document root -> $HOME/apps/studio-lemus/public
```

En hPanel, configure el document root del dominio como `apps/studio-lemus/public` si el plan lo permite. Confirme que una peticion a `https://__DOMINIO_HOSTINGER__/.env` responde 403 o 404.

### Alternativa segura con `public_html`

Mantenga Laravel fuera de `public_html`, por ejemplo en `$HOME/apps/studio-lemus`. Deje en `public_html` unicamente una referencia al directorio publico:

```bash
mv "$HOME/domains/__DOMINIO_HOSTINGER__/public_html" \
   "$HOME/domains/__DOMINIO_HOSTINGER__/public_html.initial"
ln -s "$HOME/apps/studio-lemus/public" \
      "$HOME/domains/__DOMINIO_HOSTINGER__/public_html"
```

Compruebe primero que Hostinger permite seguir symlinks. Conserve temporalmente `public_html.initial` para rollback y eliminelo despues de verificar.

Si el plan no admite symlinks ni cambiar el document root, mantenga la aplicacion en `$HOME/apps/studio-lemus`, vacie `public_html` y copie alli unicamente el contenido de `public/`. En el `public_html/index.php` copiado, sustituya las tres rutas relativas, incluida la de mantenimiento, por rutas absolutas del usuario Hostinger:

```php
if (file_exists($maintenance = '/home/[USUARIO_HOSTINGER]/apps/studio-lemus/storage/framework/maintenance.php')) {
    require $maintenance;
}
require '/home/[USUARIO_HOSTINGER]/apps/studio-lemus/vendor/autoload.php';
$app = require_once '/home/[USUARIO_HOSTINGER]/apps/studio-lemus/bootstrap/app.php';
```

No copie `.env`, `app`, `config`, `database`, `storage`, `vendor` ni el resto del proyecto a `public_html`. Sincronice de nuevo el contenido de `public/`, incluido `build`, en cada despliegue y vuelva a ajustar `index.php` despues de cada sincronizacion. Verifique que `.env`, Composer, tests, documentacion y dumps respondan 403/404.

## 4. Primer despliegue limpio por SFTP/archivo

1. Cree `$HOME/apps/studio-lemus` y suba el artefacto sin `.env`.
2. Copie `.env.production.example` a `.env` solo en el servidor y reemplace todos los marcadores entre corchetes.
3. Genere la clave una unica vez. No cambie una clave de una instalacion con datos cifrados.
4. Instale dependencias, cree el enlace de storage y migre.

```bash
cd "$HOME/apps/studio-lemus"
cp .env.production.example .env
chmod 600 .env
# Editar .env en el servidor, sin publicarlo ni pegarlo en tickets.
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan key:generate --force
php artisan storage:link
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Antes de migrar, revise `php artisan env` y confirme `production`. Elija una sola estrategia inicial.

### Base limpia

Ejecute los seeders una sola vez y cree el owner mediante entrada interactiva para no dejar contrasenas en el historial:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan studio:create-owner
```

No repita `db:seed` en despliegues normales. Revise que no existan credenciales demo antes de abrir el sitio.

### Migrar informacion local

Use el procedimiento SQL de la seccion siguiente. Exporte sin `CREATE DATABASE`, importe mediante phpMyAdmin o SSH, conserve la tabla de migraciones y revise usuarios, servicios, ventas, citas, adelantos y notificaciones antes de habilitar trafico. Nunca importe sobre una produccion existente sin backup y aprobacion explicita.

## 5. Migracion inicial de SQL local

Esta opcion se usa solo si la base local contiene datos aprobados que deben conservarse. Detenga escrituras locales durante la exportacion. No importe datos de desarrollo, sesiones, jobs o credenciales sin revisar su necesidad legal y operativa.

En la maquina local, use variables temporales para evitar credenciales en el historial:

```bash
read -r -p "Base local: " LOCAL_DB
read -r -p "Usuario local: " LOCAL_DB_USER
read -r -s -p "Password local: " LOCAL_DB_PASSWORD; printf '\n'
MYSQL_PWD="$LOCAL_DB_PASSWORD" mysqldump \
  --host=127.0.0.1 --port=3306 --user="$LOCAL_DB_USER" \
  --single-transaction --quick --routines --triggers \
  --default-character-set=utf8mb4 "$LOCAL_DB" > studio-lemus-inicial.sql
unset LOCAL_DB_PASSWORD
chmod 600 studio-lemus-inicial.sql
```

Suba el dump fuera de `public_html`. Importe en una base Hostinger vacia con un archivo de opciones privado:

```bash
chmod 600 "$HOME/.my-studio-lemus.cnf"
mysql --defaults-extra-file="$HOME/.my-studio-lemus.cnf" \
  __BASE_DE_DATOS_HOSTINGER__ < "$HOME/private/studio-lemus-inicial.sql"
cd "$HOME/apps/studio-lemus"
php artisan migrate:status
php artisan migrate --force
rm "$HOME/private/studio-lemus-inicial.sql"
```

El archivo `$HOME/.my-studio-lemus.cnf` debe ser creado manualmente fuera del repositorio:

```ini
[client]
host=__HOST_MYSQL_HOSTINGER__
port=3306
user=__USUARIO_MYSQL_HOSTINGER__
password=__PASSWORD_MYSQL_HOSTINGER__
```

No use a la vez una migracion limpia y un dump completo con el mismo esquema. Para un dump completo, importe primero y luego ejecute solo las migraciones pendientes.

## 6. Alternativa Git + SSH

Configure una deploy key de solo lectura y clone fuera de `public_html`:

```bash
mkdir -p "$HOME/apps"
git clone __URL_REPOSITORIO__ "$HOME/apps/studio-lemus"
cd "$HOME/apps/studio-lemus"
```

Prepare `.env`, el archivo MySQL privado y `public/build` como se describe arriba. Para desplegar un commit o tag explicitamente aprobado:

```bash
cd "$HOME/apps/studio-lemus"
export DEPLOY_REF=__COMMIT_O_TAG_APROBADO__
export DEPLOY_BASE_URL=https://__DOMINIO_HOSTINGER__
export DEPLOY_DB_NAME=__BASE_DE_DATOS_HOSTINGER__
export MYSQL_DEFAULTS_FILE="$HOME/.my-studio-lemus.cnf"
bash scripts/deploy-hostinger.sh
```

Si `DEPLOY_REF` esta vacio, el script despliega el checkout actual. Si tiene valor, ejecuta `git fetch` y deja el commit obtenido en detached HEAD. El operador debe aprobar el ref antes de exportarlo. El script no compila assets, no usa seeds y aborta si falta el manifest.

## 7. Permisos y tareas programadas

El script aplica permisos conservadores: `.env` y backups con `600`, codigo publico no escribible y `storage`/`bootstrap/cache` escribibles por propietario y grupo. En una instalacion manual:

```bash
cd "$HOME/apps/studio-lemus"
chmod 600 .env
find public -type d -exec chmod 755 {} \;
find public -type f -exec chmod 644 {} \;
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
chmod 755 artisan scripts/deploy-hostinger.sh scripts/smoke-test-production.sh
```

No use `chmod -R 777`. `QUEUE_CONNECTION=sync` evita depender de un worker permanente. Programe el scheduler solo cuando la aplicacion tenga tareas programadas:

```cron
* * * * * cd /home/__USUARIO_HOSTINGER__/apps/studio-lemus && __RUTA_PHP_83__ artisan schedule:run >> /dev/null 2>&1
```

El scheduler ejecuta diariamente `studio:process-payroll` y cada minuto `studio:process-expired-appointments` y `studio:dispatch-daily-close-email`. El cierre envía por SMTP dentro del mismo proceso y no requiere worker permanente. No invente la ruta del binario PHP: identifíquela por SSH con `command -v php`, confirme la versión con `php -v` y sustituya `__RUTA_PHP_83__` por el binario PHP 8.3 real ofrecido por Hostinger. No programe `studio:generate-financial-demo`; ese comando rechaza producción.

## 8. Verificacion de produccion

```bash
cd "$HOME/apps/studio-lemus"
bash scripts/smoke-test-production.sh https://__DOMINIO_HOSTINGER__
```

El smoke test solo lee: comprueba HTTPS y su redireccion, login, `/up`, assets del manifest, ausencia de trazas visibles, conexion Artisan a la base, estado de migraciones y storage. No inicia sesion ni modifica datos.

En planes Hostinger donde PHP CLI tenga `proc_open` deshabilitado, no use `php artisan about` ni `php artisan db:show`: Laravel puede invocar Symfony Process para esos comandos. Use `php artisan migrate:status` para comprobar conexion y migraciones; es compatible con el release precompilado.

Revise tambien:

```bash
php artisan env
php artisan about --only=environment
php artisan migrate:status
tail -n 100 storage/logs/laravel.log
```

## 9. Backups y rollback

- Active backups periodicos de archivos y MySQL en hPanel y verifique restauraciones.
- Antes de cada migracion, el script crea un `mysqldump` si `mysqldump` esta instalado. Por seguridad aborta si faltan `MYSQL_DEFAULTS_FILE` o `DEPLOY_DB_NAME`.
- Guarde backups fuera de `public_html`, con permisos `600`, retencion definida y una copia fuera de la cuenta Hostinger.
- Las migraciones pueden ser irreversibles. Revise el SQL y pruebe restauracion antes de produccion.

Rollback de codigo a un commit aprobado:

```bash
cd "$HOME/apps/studio-lemus"
export DEPLOY_REF=__COMMIT_ANTERIOR_APROBADO__
export DEPLOY_BASE_URL=https://__DOMINIO_HOSTINGER__
export DEPLOY_DB_NAME=__BASE_DE_DATOS_HOSTINGER__
export MYSQL_DEFAULTS_FILE="$HOME/.my-studio-lemus.cnf"
bash scripts/deploy-hostinger.sh
```

Si el release nuevo migro datos de forma incompatible, poner mantenimiento, restaurar primero el dump asociado y despues desplegar el commit anterior:

```bash
php artisan down --retry=60
mysql --defaults-extra-file="$HOME/.my-studio-lemus.cnf" \
  __BASE_DE_DATOS_HOSTINGER__ < "$HOME/backups/studio-lemus/__BACKUP_APROBADO__.sql"
# Desplegar el commit anterior con el bloque precedente.
php artisan up
```

No use `migrate:rollback` a ciegas en produccion. Documente commit, backup, hora, operador y resultado del smoke test para cada despliegue.

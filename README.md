# Studio Lemus

Aplicación Laravel 13 + Vue/Inertia para agenda, ventas, comprobantes, gastos, ganancias, configuración y cierres diarios de Studio Lemus.

## Requisitos

- PHP 8.3 con `pdo_mysql` y `pdo_sqlite` para las pruebas.
- Composer 2.
- Node.js compatible con Vite 8.
- MySQL para desarrollo/producción.

## Instalación local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
```

El seeder local/testing crea la configuración inicial del cierre a las `21:00`, zona `America/Tegucigalpa` y envío automático desactivado. Las credenciales SMTP y destinatarios se configuran desde la interfaz.

## Cierre diario

La arquitectura usa:

- `daily_close_settings`: hora, SMTP cifrado, remitente, destinatarios y activación.
- `daily_close_setting_events`: auditoría append-only de cambios.
- `daily_close_reports`: historial por destinatario, estado, intentos, PDF y error sanitizado.
- `DailyCloseReportData`: consume las acciones financieras existentes sin duplicar fórmulas.
- `DailyClosePdfGenerator`: genera con Dompdf y guarda en el disco privado `daily_closures`.
- `DailyCloseReportMail`: correo breve con resumen y PDF adjunto desde almacenamiento privado.
- `studio:dispatch-daily-close-email`: revisión síncrona de configuraciones vencidas, sin worker permanente.

Los PDFs viven bajo `storage/app/private/daily-closures`. No se enlazan con `storage:link` y solo se descargan mediante una ruta autenticada con `daily_close.view`.

### SMTP y cron

Host, puerto, usuario, contraseña, TLS/SSL, remitente y destinatarios se administran en `Configuración > Cierre diario`. La contraseña usa el cast cifrado de Laravel y nunca se devuelve al frontend. Si el campo queda vacío al editar, se conserva el valor existente.

En hosting compartido, programa un solo cron cada minuto:

```cron
* * * * * cd /ruta/studio-lemus && php artisan schedule:run >> /dev/null 2>&1
```

El cierre se procesa dentro del scheduler y no requiere worker. Revisa `daily_close_settings.send_time` en `America/Tegucigalpa`; la clave única de fecha + correo evita duplicados y `--force` crea un reenvío manual explícito.

### Operación manual

```bash
php artisan studio:send-daily-close-email --date=2026-07-28
php artisan studio:send-daily-close-email --date=2026-07-28 --force
php artisan studio:dispatch-daily-close-email
```

También puede generarse, descargarse y enviarse desde Ganancias o desde `Configuración > Cierre diario`.

## Permisos

- `daily_close.view`: ver configuración, historial y descargar PDFs.
- `daily_close.manage`: modificar hora, SMTP, remitente, activación y destinatarios.
- `daily_close.send`: solicitar pruebas y envíos manuales.

Owner recibe los tres permisos. Otros roles solo acceden si se les asignan explícitamente. Los endpoints validan usuario activo, permiso, modelo solicitado y correos únicos; los envíos de prueba/manual tienen rate limit.

## Validación

```bash
php artisan test
vendor/bin/pint --test
npm run lint
npm run typecheck
npm run format:check
npm run build
composer validate --strict
```

Las pruebas usan SQLite en memoria, almacenamiento privado fake y un emisor SMTP simulado; nunca conectan con un servidor externo. MySQL sigue siendo necesario para validar planes de consulta y bloqueos de producción.

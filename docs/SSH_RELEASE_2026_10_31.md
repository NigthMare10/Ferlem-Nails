# Publicación SSH — 2026-10-31

Base descargada de GitHub sin conflictos: `a762134` (fidelidad, regalos y rediseño del portal).

Antes de publicar se detectó que la recuperación de contraseña permitía cambiar una clave únicamente conociendo el correo. Con autorización explícita se restauró el envío de enlace mediante el broker exclusivo de clientas. La respuesta no revela si existe la cuenta; cambiar la clave exige token válido, con vencimiento y de un solo uso. La pantalla solicita solo correo hasta abrir ese enlace.

También se corrigió el empaquetado para incluir `public/fonts/stitch` y `public/images/stitch`, publicándolos en el document root de Hostinger con respaldo y recuperación. Ambos seeders QA quedan fuera del paquete, al igual que `.env`, SQLite, tests, cachés locales y dependencias Composer de desarrollo. La conciliación incluye las tablas de clientas y fidelidad existentes, además de las operativas.

Validación local:

- PHP: **536 pruebas y 4,558 aserciones** aprobadas.
- Playwright: **15 recorridos de fidelidad + 1 recuperación segura** aprobados.
- TypeScript y compilación de producción: aprobados.
- Composer y npm de producción: sin avisos conocidos. La auditoría completa npm reporta 12 avisos moderados en herramientas de desarrollo; no se aplicaron actualizaciones mayores ajenas al alcance.
- Detector de la pantalla de acceso: 67 avisos de diseño advisory contra la paleta/tipografía Stitch ya aprobada; no se presenta como una certificación de accesibilidad.

Se utilizan `scripts/build-reviewed-release.ps1` y `scripts/publish-reviewed-release.py`, con verificación de la clave del servidor y respaldo privado previo. No se copian datos de la otra PC ni se ejecutan seeders o el comando de reconfiguración de niveles en producción.

El cron de hPanel sigue siendo necesario para ejecución desatendida; esta publicación no configura hPanel ni acredita pruebas físicas de cámara/Safari o entrega de correo real.

## Resultado en Hostinger

- Publicación SSH completada con las migraciones `000600`, `000700` y `000800`.
- Respaldo privado verificado: `/home/u357586881/lemus-releases/release-20261031235219/backup`.
- Los conteos de las tablas operativas, clientas y fidelidad existentes coinciden antes/después.
- 151 assets compilados, fuentes e imágenes: HTTP 200.
- Login, registro, recuperación y salud: HTTP 200. Las rutas administrativas sin sesión redirigen al login; `.env` devuelve 403.
- Revisión pública a 390px sin errores JavaScript, HTTP de assets ni desbordamiento horizontal. La recuperación solo solicita correo; no muestra campos de contraseña antes de verificar el enlace.
- Se detectó y corrigió un permiso 700 heredado de la máscara privada en las carpetas padre públicas `fonts` e `images`; ahora son 755. El script incluye la corrección y smoke tests de fuente/imagen para futuras publicaciones.

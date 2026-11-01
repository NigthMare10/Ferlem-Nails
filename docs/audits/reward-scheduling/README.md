# Registro inmediato, comidas y citas de premio

## Alcance

- El registro no envía correo de verificación ni exige verificar para QR, canjes, regalos o puntos. No se marca falsamente el correo como verificado. Se conserva la recuperación con token.
- El encabezado de Inicio evita la marca duplicada y reserva espacio para nombres largos y controles a 320/390 px.
- Las comidas se configuran en una modal de lunes a domingo, con excepciones por fecha y bloqueos adicionales conservados.
- Los premios admiten imagen JPG/PNG/WebP de hasta 2 MB y 4096 px por dimensión, reencodificada a WebP privado. Sin imagen se utiliza un icono genérico.
- Inicio muestra hasta 20 canjes de servicios pendientes; el administrador agenda con identidad y servicio precargados. Se actualiza cada 30 segundos.
- Una cita de premio conserva el canje más allá de su vencimiento original, tiene líneas con precio cero, no admite adelantos ni checkout y se completa sin venta ni puntos por compra.
- Cancelación/inasistencia devuelve puntos y stock una vez. No se permite cancelar por separado un canje con cita activa.

## Verificación

Pruebas backend: `tests/Feature/RewardAppointmentsTest.php` y suite completa mediante `scripts/test-local.ps1`.

Resultado local: **546 pruebas / 4.637 aserciones, todas correctas**. TypeScript y compilación de producción correctos. Vite conserva los avisos ya existentes sobre las fuentes estáticas de Stitch, servidas desde `public/fonts/stitch`.

Pruebas de navegador: `tests/browser/loyalty-v2/z-reward-scheduling.spec.ts`, junto al recorrido existente de fidelidad, en SQLite QA aislado. Las capturas se guardan en esta carpeta.

Resultado final de navegador: **19/19 correctas**, incluyendo recuperación segura, regresión de fidelidad, registro inmediato, nombres largos a 320/390 px, guardado semanal de comidas, subida de imagen y reserva → Inicio → cita de total cero sin cobro. Se inspeccionaron las capturas móviles y la agenda. Una pasada focalizada posterior del backend de premios también pasó **10/10, 79 aserciones**, añadiendo comprobaciones de que reprogramar mantiene el importe cero.

Revisión estática Impeccable sobre todos los destinos visuales modificados: 107 avisos del sistema visual y 4 advertencias. Las 4 advertencias corresponden a estilos preexistentes en `Appointments/Index.vue` (bordes laterales y jerarquía tipográfica) y `Home.vue` (jerarquía tipográfica). Los avisos de paleta/tipo del portal se revisaron frente al mundo Stitch aprobado en `DESIGN.md`; no se reemplazó por el tema administrativo. Este detector no certifica accesibilidad en tiempo de ejecución.

## Publicación

Implementación `db6e477` subida a `main` y desplegada en Hostinger el 2026-11-01 UTC, con autorización del usuario. Se aplicó `2026_11_01_000000_add_reward_images_and_appointments`; los conteos de datos existentes permanecieron iguales. No se desplegaron credenciales ni datos QA.

- Respaldo privado verificado: `/home/u357586881/lemus-releases/release-20261101003515/backup`.
- Comprobación pública de solo lectura: **153/153 assets HTTP 200**.
- Login administrativo, login/registro/recuperación de clientas y `/up`: HTTP 200 sin trazas de depuración.
- `/clientes` y `/appointments` sin sesión: redirección HTTP 302; `/.env`: HTTP 403.
- No se crearon cuentas, canjes, citas ni ventas de prueba en producción.

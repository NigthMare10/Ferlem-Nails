# Fidelidad v2 — implementación y verificación

Fecha: 2026-10-30. Cambios locales, sin commit ni publicación.

## Resultado comprobado

- Suite PHP completa: **533/533**, **4.540 aserciones**. Incluye **117 casos nuevos** en `tests/Feature/LoyaltyV2Test.php`. Resultado: `php-results.json`.
- Navegador: **15/15 E2E**, cero omitidos, inesperados o flaky en la ejecución final. Chromium visible, solicitudes reales a Laravel y SQLite QA aislada. Resultado: `e2e-results.json`.
- Concurrencia MySQL local: dos reservas simultáneas por el último cupo dejan una sola reserva; tres envíos simultáneos con el mismo UUID dejan un lote y un asiento. Saldo esperado 175, stock 0. Resultado: `mysql-concurrency.json`.
- TypeScript, build de producción y ESLint de los componentes afectados: correctos. El build mantiene advertencias previas sobre rutas de fuentes resueltas en runtime.
- Capturas revisadas: `admin-final.png` y `client-final.png`. El escritorio conserva sidebar/topbar; el portal conserva Liquid Glass.

## Funcionalidad

- Premios vinculados a servicios existentes, costo de canje separado de los puntos por compra, existencias, fechas, cumpleaños y límite por clienta.
- Edición con versión optimista y pausa/reactivación múltiple atómica; reservas existentes e historial se conservan.
- Historial con búsqueda, filtros, detalle, entrega y cancelación con motivo. Reservar debita una vez; entregar no vuelve a debitar; cancelar o vencer restituye una vez.
- Niveles editables por propietaria con revisión; nombres y umbrales válidos, protección ante versiones antiguas. El máximo no muestra un siguiente nivel ficticio.
- Regalos de puntos o servicios para una o varias clientas, búsqueda y selección de página/filtro, revisión, resultados por destinataria y reintento de pendientes/fallidas.
- Los puntos regalados aumentan el saldo canjeable, no los puntos calificables. Los servicios regalados son vales auditados, sin cobro ni puntos por compra, con vigencia opcional, entrega y cancelación.
- Regalos disponibles siempre visibles en el portal, independientemente de la paginación histórica; conciliación mientras el portal está visible.
- Crear/reintentar regalos requiere `loyalty.manage` y `loyalty.adjust`; resolver servicios requiere `loyalty.manage` y `loyalty.redeem`. El rol administrador por defecto no tiene `loyalty.adjust`: debe autorizarse explícitamente si necesita enviar regalos.

## Errores encontrados y corregidos

1. Carrera MySQL al insertar el mismo UUID bajo snapshots de lectura: relectura tras rollback y comprobación de actor/hash inmutable.
2. Cancelar una reserva creada con stock ilimitado después de cambiar a finito inventaba existencias: ahora se conserva si la reserva retuvo stock.
3. Regalos disponibles podían quedar ocultos tras regalos históricos paginados: colección activa independiente.
4. Cambios simultáneos podían sobrescribir premios/niveles: control de versiones y actualización masiva atómica.
5. Nombres de servicios desactualizados: identidad actual heredada y snapshots pasados inmutables.
6. Eliminación de servicios vinculados: error de validación comprensible en lugar de fallo de integridad SQL.
7. Accesibilidad: iconos decorativos fuera del nombre accesible; icono de regalo SVG para evitar dependencia de un glifo ausente.
8. Fecha de vales interpretada explícitamente en Honduras; entrega rechazada al vencer.

## Cobertura y reproducción

```powershell
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit tests/Feature/LoyaltyV2Test.php
node scripts/run-loyalty-e2e.mjs
node scripts/run-loyalty-mysql.mjs
npm run typecheck
npm run build
```

Los E2E cubren el ciclo clienta/administración, dos recompensas, edición posterior a reserva, pausa/reactivación múltiple, nivel máximo, regalos a dos clientas, entrega/cancelación, respuesta perdida y reenvío, validación, descartar/Escape y las cuatro secciones a 320/768/1440 px. El tooltip de validación HTML puede consumir Escape; las pruebas de validación y Escape se separan reabriendo la ventana.

Las pruebas PHP incluyen límites de datos/lotes, permisos, reactivación y reintentos parciales, privacidad, instantáneas, expiración, transiciones terminales, paginación, versiones antiguas y contabilidad. No equivalen a todos los escenarios posibles.

`run-loyalty-e2e.mjs` crea una SQLite nueva y ejecuta el servidor en 8012. `run-loyalty-mysql.mjs` crea un esquema local `loyalty_qa_<timestamp>` y lo conserva; no borra bases ni usa saldos reales. La sonda es pequeña, no una prueba de carga productiva.

## Configuración local

Se respaldó fidelidad en `storage/app/.old/loyalty-20261030-193928.json` antes de aplicar las migraciones 000700 y 000800 a MySQL local. `loyalty:configure-approved-levels` cambió únicamente la configuración inicial intacta a Brillo 0, Glow 100, Amatista 1000, Diamante 5000, sin alterar saldos.

Premios y regalos de prueba se crearon en QA aislada. No se distribuyeron cortesías ficticias a clientas reales ni se eligieron arbitrariamente costos comerciales de premios para la base principal.

## Revisión Impeccable

`detector.json`: 19 hallazgos estáticos, **18 advisory y 1 warning**, sin errores mecánicos reportados. Doce corresponden a color, dos a radios, cuatro a tamaños y uno a Noto Serif. Revisión contra `DESIGN.md`, secciones Customer portal / Approved Stitch source: Noto Serif y los colores Liquid Glass ya están aprobados en prosa; el detector no los reconoce todos en el frontmatter. Los tamaños compactos y radios de contenido de estos objetivos son excepciones de la implementación Stitch; no se alteró el shell para silenciar el detector. El código de salida 1 no se presenta como un escaneo limpio. No es certificación de contraste ni de accesibilidad con lector de pantalla. No se reescribió DESIGN.md ni sus tokens globales para absorber diferencias de una superficie local.

## Límites y mejoras prioritarias

1. **Seguridad antes de publicar:** la recuperación de contraseña previa basada solo en correo, solicitada anteriormente, sigue siendo insegura. Sustituirla por token de un solo uso y vencimiento.
2. Activar y verificar el scheduler de producción (`schedule:run`); la definición local existe, pero su ejecución productiva no está comprobada.
3. Los vales no crean citas ni se aplican automáticamente en checkout; integrar ambas operaciones sería una mejora útil con nuevas reglas contables.
4. Regalos solo visibles en portal: no hay notificaciones externas. Añadir avisos opt-in y recordatorios de vencimiento.
5. Considerar historial/exportación de cortesías y límites/aprobación por importe para campañas grandes; el procesamiento actual es síncrono, máximo 500 destinatarias.
6. No hubo MCP Playwright disponible: se usó Playwright instalado con Chromium visible. Móvil emulado, no teléfonos físicos ni Safari/iOS real. Pendiente prueba con dispositivos y carga productiva.

La galería Stitch en `docs/design/loyalty-v2/` es referencia del diseño aprobado, no una afirmación de equivalencia pixel-perfect de los 28 estados. Scripts de revisión anteriores a v2 pueden conservar etiquetas antiguas; la suite vigente es `tests/Browser/loyalty-v2/loyalty.spec.ts`.

# Fidelidad v2 — propuesta pendiente de aprobación

**No se sustituyó ni eliminó ninguna vista de la aplicación.** Solo se crearon maquetas en Stitch y este paquete de revisión. Los archivos HTML exportados son referencias sin integración ni lógica de servidor; no se deben copiar a producción directamente.

- Proyecto: https://stitch.withgoogle.com/projects/15003048259766237811
- Galería completa: `index.html` (28 estados distintos, con imágenes locales que no dependen de la agrupación del canvas remoto).
- Vista local: http://127.0.0.1:8020/ mientras esté activo el servidor de revisión.
- Los prefijos FINAL/APROBACION son nombres de revisiones generadas por Stitch; **no significan aprobación del usuario**.
- Las revisiones principales congeladas son FINAL 01 y APROBACION 02/05/10/13/28. Los originales y revisiones intermedias pueden seguir apareciendo en el canvas. La API de Stitch no agrupó consistentemente todos los estados; la galería local reúne las 28 propuestas para que ninguna modal quede inaccesible.

## Propuesta funcional

1. **Premios:** seleccionar un servicio ya creado, heredar su identidad y consultar duración/precio/puntos por compra, configurar puntos de canje, cupos, vigencia y límite total por clienta. No duplicar el catálogo de Servicios ni confundir puntos ganados con puntos gastados.
2. **Historial:** búsqueda/filtros, detalle de reserva, entrega, cancelación con devolución y estado vencido. Reservas normales de 24 horas, devolución una sola vez y entrega sin nuevo débito.
3. **Niveles:** nombres, umbrales distintos de puntos calificables y descripción de beneficios. Primer nivel desde cero. Sin multiplicadores, descuentos o prioridad automática inventados.
4. **Regalos:** seleccionar una o varias clientas, escoger puntos o un servicio existente, motivo/mensaje opcional, revisar destinatarias y total antes de confirmar, consultar resultado por clienta y reintentar solo pendientes. Servicio de cortesía: sin puntos cobrados ni generados por compra. Entrega/cancelación del vale con confirmación y auditoría.

## Decisiones pendientes

- Se propone que regalar puntos incremente solo el saldo disponible y no los puntos calificables del nivel. El usuario debe confirmar esta política.
- Se propone un servicio por clienta seleccionada, mismo beneficio para todo el lote, con vencimiento opcional del vale. La vigencia del regalo no se debe confundir con las 24 horas de una reserva normal.
- Los textos de beneficios, nombres, cuentas, precios, fechas y cifras son ejemplos de diseño, no datos de la base ni compromisos comerciales.
- No se promete envío por WhatsApp, correo o SMS: el mensaje mostrado es solo contenido/previsualización de la propuesta.
- No hay reinicio mensual de límite de canjes. No trasladar textos contradictorios de revisiones antiguas.

## Límites visuales y revisión

El sidebar, logo y topbar existentes de la aplicación quedan **fuera del rediseño**. Stitch produjo variaciones incidentales en esos elementos; no deben implementarse. La aprobación se solicita para el contenido y los flujos de fidelidad. Tampoco se trasladan los rótulos APROBACION/FINAL al título real de la pantalla.

Se inspeccionaron las seis capturas principales exportadas; se solicitó a Stitch corregir tablas cortadas, controles sin destino, periodo mensual inventado, regla de 48 horas y multiplicadores de nivel no existentes. Las imágenes finales son propuestas, no evidencia de accesibilidad o funcionalidad de una futura implementación. Los estados secundarios heredan sus reglas del resumen anterior aunque conserven cifras o fondos ilustrativos de rondas anteriores.

El detector Impeccable ejecutado sobre los seis HTML de referencia informó 19 advertencias (6 sobre fuente de iconos frente al sistema administrativo, 2 de jerarquía tipográfica y 11 de contenedores anidados) y 48 avisos de tokens. No constituye un pase de producción. Estos exports se conservan como salieron de Stitch; las advertencias deberán resolverse al implementar después de aprobación. `detector.json` conserva los hallazgos; el proceso también devolvió un código operativo 1, por lo que no se afirma cobertura completa del análisis.

## Referencias principales de Stitch

| Vista | Screen ID |
| --- | --- |
| Premios | `b95c750ee19942028ae615d511159f59` |
| Añadir premio | `81bb4d1610a440a1a06880fb2658ca06` |
| Selector abierto | `58184e9a5a994bf1bad5cba7ac4f3f37` |
| Historial | `b06e5d2afa6f4297899ed15597fa274b` |
| Niveles | `aabeb3c8d607487e8b2e88af2ac71e69` |
| Regalos | `e2bc4c2866e24a9891606ad78f739c97` |
| Regalo móvil | `4b7cb87b70d349bdba67dce0fc96ac37` |

Origen de los PNG: capturas generadas por Stitch para este proyecto, descargadas mediante `Stitch.download_file`; no son capturas de la aplicación implementada. `sources.json` registra recursos de los 22 estados secundarios; los seis principales corresponden a los IDs de pantalla de esta tabla y sus exports HTML homónimos. Ningún archivo visual de esta propuesta sustituye los aprobados anteriormente hasta que el usuario lo confirme.

La galería se verificó en Chromium: 28 imágenes cargadas y decodificadas, sin desbordamiento horizontal del índice a 390px. Capturas `gallery-desktop.png` y `gallery-mobile.png`. Este control valida la galería de propuestas, no el comportamiento futuro de los formularios. El detector adicional del índice terminó con código 0; los hallazgos propios de los exports de Stitch siguen documentados arriba.

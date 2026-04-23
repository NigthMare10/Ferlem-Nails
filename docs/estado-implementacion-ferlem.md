# Estado de implementacion FERLEM

Ultima actualizacion: 2026-04-22

## Pantallas estables que no deben tocarse sin una solicitud puntual

- Inicio de Cobro: estable segun alcance actual.
- Detalle de Cobro y Pago: estable segun alcance actual.
- Login operativo por perfiles: funcional con altas reales de empleados, aparicion en selector y autenticacion por contraseña.
- Gestion de Empleados Admin: estable para crear, editar, eliminar y descartar con persistencia real.
- Ajuste de Precios Admin: estable con alta/edicion de servicios, analitica, configuracion y salir funcionales.
- Reportes de Ventas Analytics: estable con navegacion al rendimiento del empleado especifico.
- Rendimiento por Empleado: estable en su flujo principal con resumen, ganancias, exportacion e historial completo.
- Otras pantallas fuera de esta iteracion: no tocar salvo dependencia directa documentada.

## Pantallas en revision en esta iteracion

- Detalle de Factura Digital: correccion puntual aplicada en contexto usuario normal y admin sobre Imprimir real desde el iframe, Volver segun contexto, engranaje con Salir real, navegacion admin, perfil sin imagen rota y hora local de Tegucigalpa.
- Historial de Facturas: correccion puntual aplicada con busqueda y filtro real por empleado/folio/referencia/estado/monto, Exportar CSV filtrable, Ver Factura correcta, engranaje con Salir y sin botones muertos.
- Analitica y suite de rendimiento por empleado: ahora deben consumir metricas reales unificadas desde facturas y detalle_facturas por sucursal activa; no volver a reintroducir arrays mock en reportes, rendimiento, ganancias ni historial completo.
- Pantallas genericas Stitch no incluidas en esta iteracion: mantener sin cambios salvo requerimiento explicito.

## Riesgos / roturas detectadas

- Las pantallas Stitch genericas en iframe siguen dependiendo de mejoras inyectadas en `resources/js/Pages/Stitch/Frame.tsx`; para esta iteracion solo se toco puntualmente Detalle de Factura Digital e Historial de Facturas, y la suite admin se conecto a metricas reales compartidas.
- La verificacion backend debe ejecutarse con `C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64\php.exe`; no usar el PHP 8.3 del PATH para pruebas del proyecto.

## Restauraciones confirmadas antes de esta iteracion

- Reportes de Ventas Analytics ya fue regresada a una estructura visual mas fiel a Stitch en la iteracion previa inmediata.
- En esta correccion puntual se reparo Detalle de Factura Digital con impresion real desde el iframe, Volver segun contexto, engranaje con Salir real, navegacion admin propia de esa vista, imagen rota de perfil, eliminacion de metodo/tarjeta y hora local sin redisenar la pantalla.
- En esta correccion puntual se reparo Historial de Facturas con datos reales, busqueda/filtro funcional, acceso a factura digital, exportacion CSV filtrable, engranaje con Salir y navegacion util hacia Analitica e Historial.
- En esta correccion puntual se unifico la fuente de verdad admin mediante `app/Modules/Stitch/Services/AdminMetricsService.php` para Historial de Facturas, Reportes Analytics, Rendimiento por Empleado, Ganancias e Historial Completo.
- En esta iteracion se reemplazo la logica frontend simulada de empleados y precios por persistencia real en backend.

## Notas de control para no volver a romper

- No redisenar pantallas Stitch.
- Mantener todo lo visible en espanol.
- Mantener moneda en lempiras donde aplique.
- No tocar el flujo normal salvo los puntos puntuales pedidos para Detalle de Factura Digital, Historial de Facturas y metricas admin reales asociadas a facturas.
- Mantener una sola fuente de verdad admin basada en `facturas`, `detalle_facturas`, `pagos`, `usuario/empleado` y sucursal activa.
- Reutilizar `resources/js/lib/adminNavigation.ts` y `resources/js/Components/admin/AdminSettingsMenu.tsx` para evitar rutas o logout inconsistentes entre pantallas admin.

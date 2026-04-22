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

- Detalle de Factura Digital: correccion puntual aplicada en contexto usuario normal y admin sobre Imprimir, Volver segun contexto, engranaje con Salir real, navegacion admin, perfil sin imagen rota y hora local de Tegucigalpa.
- Pantallas genericas Stitch no incluidas en esta iteracion: mantener sin cambios salvo requerimiento explicito.

## Riesgos / roturas detectadas

- Las pantallas Stitch genericas en iframe siguen dependiendo de mejoras inyectadas en `resources/js/Pages/Stitch/Frame.tsx`; para esta iteracion solo se toco Detalle de Factura Digital en sus contextos de usuario normal y admin.
- La verificacion backend debe ejecutarse con `C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64\php.exe`; no usar el PHP 8.3 del PATH para pruebas del proyecto.

## Restauraciones confirmadas antes de esta iteracion

- Reportes de Ventas Analytics ya fue regresada a una estructura visual mas fiel a Stitch en la iteracion previa inmediata.
- En esta correccion puntual unicamente sobre Detalle de Factura Digital se reparo Imprimir, Volver segun contexto, el engranaje con Salir real, la navegacion admin propia de esa vista, la imagen rota de perfil, la eliminacion de metodo/tarjeta y la hora local sin redisenar la pantalla.
- En esta iteracion se reemplazo la logica frontend simulada de empleados y precios por persistencia real en backend.

## Notas de control para no volver a romper

- No redisenar pantallas Stitch.
- Mantener todo lo visible en espanol.
- Mantener moneda en lempiras donde aplique.
- No tocar el flujo normal salvo los puntos puntuales pedidos para Detalle de Factura Digital.
- Reutilizar `resources/js/lib/adminNavigation.ts` y `resources/js/Components/admin/AdminSettingsMenu.tsx` para evitar rutas o logout inconsistentes entre pantallas admin.

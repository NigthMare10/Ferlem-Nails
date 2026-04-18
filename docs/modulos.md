# Módulos del sistema

## IdentidadAcceso
- Login, logout y sesión segura.
- Acceso inicial por selección de perfil operativo.
- Reautenticación administrativa con TTL por sucursal.
- Exposición controlada de roles, permisos y sucursal activa al frontend.

## Sucursales
- Gestión de contexto activo.
- Asociación usuario-sucursal.
- Configuración operativa por sucursal.
- Aislamiento backend real vía middleware, policies y servicios.

## Configuración
- En esta base sigue absorbido por `Sucursales`.
- La configuración crítica del MVP vive en `ConfiguracionSucursal` y `SecuenciaDocumento`.

## Clientes
- Cliente maestro + perfil operativo por sucursal.
- Alta completa y alta rápida desde POS.
- Autorización por policy y auditoría en creación/edición.
- En el MVP operativo actual no participa del flujo principal de cobro para usuarios no admin.

## Empleados
- Empleado operativo, relación opcional con usuario.
- Scoping por sucursal en listados y asignaciones.
- Policy explícita de visualización.

## Catálogo
- Categorías, servicios, precios por sucursal e historial mínimo.
- Base preparada para endurecer cambios manuales de precio en fase posterior.

## Agenda
- Citas con cliente, servicio, empleado y sucursal.
- Validación de que el empleado pertenezca a la sucursal y pueda ejecutar el servicio.

## VentasPOS
- Flujo operativo no admin alineado a Stitch: `Inicio de Cobro` -> `Detalle de Cobro y Pago`.
- La operación se asocia al perfil autenticado del empleado mediante `user_id`.
- Protección de descuentos extraordinarios por permiso + reautenticación.
- Validación de que empleados/servicios pertenezcan al contexto correcto.

## Pagos
- Registro de pagos manuales con idempotencia.
- Validación estricta de consistencia entre orden, sucursal y sesión de caja.

## Facturación
- Emisión desde orden pagada.
- Snapshot inmutable del detalle.
- Policy y validaciones de sucursal en visualización y emisión.

## Caja
- Apertura, sesión activa, movimientos y cierre.
- Cierre ajeno protegido por permiso específico `caja.cerrar_ajena`.
- Reautenticación obligatoria para cierres sensibles.

## Reportes
- Resumen ligero por sucursal.
- Permisos diferenciados entre visibilidad de sucursal y futura visibilidad global.

## Auditoría
- Registro de autenticación, sucursal, clientes, agenda, POS, caja, pagos y facturación.
- Sanitización básica de metadata sensible.

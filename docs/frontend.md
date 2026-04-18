# Frontend

## Stack
- Inertia + React + TypeScript
- Tailwind CSS con identidad visual cálida y editorial
- Shell principal reutilizable para administración, POS y reportes

## Shells implementados
- `AuthShell`: acceso, recuperación y reautenticación.
- `AppShell`: navegación principal con variante `admin`, `pos` y `reportes`.

## Páginas operativas disponibles
- Acceso operativo por perfiles
- Selector de sucursal
- 11 pantallas Stitch activas
- 2 referencias de acceso adicional detectadas en `references`: selección de perfil e ingreso de contraseña

## Principios aplicados
- Todo el copy principal está en español.
- La moneda visible es HNL/L.
- El POS operativo del MVP no usa cliente final; la operación se asocia al perfil autenticado del empleado.
- Para usuarios operativos no admin, las pantallas de `Inicio de Cobro` y `Detalle de Cobro y Pago` se acercaron al layout, ritmo visual y simplicidad de Stitch con cambios minimos para backend, seguridad y navegacion real.

## POS operativo
- `resources/js/Pages/Pos/Index.tsx` replica en una sola vista funcional los dos estados principales de Stitch:
  - `Inicio de Cobro`
  - `Detalle de Cobro y Pago`
- Los usuarios no admin quedan enfocados en estas dos pantallas y no ven modulos administrativos.
- La pantalla inicial conserva la seleccion grande por categoria.
- La pantalla de detalle conserva el layout de menu a la izquierda y resumen/pago a la derecha.
- No existe pantalla previa de cliente ni modal de cliente en el flujo operativo actual.
- Los ajustes tecnicos visibles se limitaron a lo minimo para navegacion real, checkout y seguridad.
- No hay captura real de tarjeta ni CVV; la UI conserva la intencion visual basica y el backend procesa un pago simplificado.

## Pantallas Stitch activas en rutas
- `/inicio-de-cobro`
- `/detalle-de-cobro-y-pago/{categoria}`
- `/historial-de-facturas`
- `/detalle-de-factura-digital/{factura?}`
- `/detalle-de-factura-premium/{factura?}`
- `/cierre-de-caja-diario`
- `/lista-de-empleados`
- `/rendimiento-por-empleado`
- `/reportes-de-ventas-analytics`
- `/gestion-de-empleados-admin`
- `/ajuste-de-precios-admin`

## Navegación frontend Stitch
- Las pantallas administrativas renderizadas desde `references` usan `resources/js/Pages/Stitch/Frame.tsx`.
- El frontend reescribe enlaces internos de Stitch para conectarlos a rutas reales del proyecto.
- También corrige texto visible, marca, moneda y comportamiento de scroll dentro del documento embebido.
- La acción `Salir` dentro de pantallas Stitch ejecuta logout real y vuelve a `/login`.
- Los menús principales del perfil `admin` ya navegan entre varias pantallas reales de Stitch.

## Scroll y layout
- El frame Stitch ya no recalcula altura dinámicamente.
- Cada pantalla embebida usa altura estable de viewport y scroll interno real, evitando crecimiento infinito.
- No se rediseñó la UI; solo se habilitó continuidad visual y navegación funcional.

## Flujo de acceso actual
- La vista inicial muestra perfiles operativos visibles del personal activo.
- Solo se listan usuarios activos con al menos una sucursal activa asignada.
- Si el usuario tiene un empleado asociado e inactivo, el perfil no se muestra.
- El segundo paso pide únicamente la contraseña del perfil seleccionado.
- La redirección posterior depende de rol, permisos y resolución de sucursal.

## Redirección inicial por perfil
- `super_admin`, `admin_negocio`, `gerente_sucursal` -> `dashboard`
- `auditor` -> `reportes`
- `cajero` -> `pos`
- `tecnica`, `recepcionista` -> `pos`
- Si el usuario tiene varias sucursales sin default -> selector de sucursal

## Elementos decorativos que siguen sin destino real
- `Pending`, `Settled`, `Refunds` en historial de facturas
- `Appointments`, `Clients`, `Services`, `Inventory` en factura premium
- `Privacy`, `Terms of Service`, `Contact` en detalle de cobro y pago

Estos elementos permanecen decorativos porque las referencias no traen una pantalla destino adicional en esta fase.

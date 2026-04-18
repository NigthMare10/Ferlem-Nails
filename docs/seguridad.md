# Seguridad

## Estado actual
La base de FERLEM NAILS ya opera con autenticación, sucursal activa, RBAC, reautenticación administrativa, auditoría y pruebas específicas de aislamiento por sucursal.

## Contexto operativo del MVP
- El flujo principal no admin no usa cliente final en esta fase.
- El contexto de cada operación es el perfil autenticado del empleado.
- La trazabilidad del cobro se conserva con `user_id`, scoping por sucursal y auditoría.

## Controles implementados
- Acceso operativo por perfil visible + contraseña real.
- Autenticación first-party con sesión web y Sanctum preparado para API stateful.
- Password hashing con el cast `hashed` de Laravel.
- Roles y permisos con Spatie Permission.
- Policies por recurso para clientes, citas, empleados, órdenes, facturas, sesiones de caja y sucursales.
- Middleware `active.branch` para resolver y aislar el contexto operativo.
- Middleware `admin.reauth` para exigir confirmación reciente en acciones sensibles.
- Rate limiting para login, cambio de sucursal y acciones sensibles.
- Auditoría consistente en eventos de autenticación, sucursal, clientes, agenda, POS, caja, pagos y facturas.

## Matriz base de roles y permisos

### `super_admin`
- Acceso total.
- Puede operar en todas las áreas y ver auditoría.

### `admin_negocio`
- Acceso operativo completo del MVP.
- Puede gestionar configuración, precios, roles y cierres de caja ajenos.

### `gerente_sucursal`
- Puede gestionar clientes, agenda, empleados, catálogo, caja, POS, pagos, facturas, reportes de sucursal y auditoría.
- Puede aplicar descuentos extraordinarios con reautenticación.
- Puede cerrar cajas abiertas por otros usuarios de su sucursal.

### `cajero`
- Puede operar POS, registrar pagos, emitir facturas, abrir/cerrar su propia caja y gestionar clientes básicos.
- No puede ver reportes ni cerrar cajas de terceros.
- No puede aplicar descuentos extraordinarios.

### `tecnica`
- Puede ver agenda, catálogo y empleados de la sucursal.
- No puede operar POS, pagos, caja ni reportes.

### `recepcionista`
- Puede ver/crear/editar clientes.
- Puede crear y gestionar agenda base.
- Puede consultar facturas, pero no cobrar ni operar caja.

### `auditor`
- Puede consultar facturas, caja, reportes y auditoría.
- No puede operar POS ni modificar información operativa.

## Permisos sensibles destacados
- `pos.aplicar_descuento_extraordinario`
- `catalogo.cambiar_precio`
- `caja.cerrar_ajena`
- `caja.reabrir`
- `facturas.anular`
- `facturas.exportar`
- `auditoria.ver`
- `configuracion.gestionar`
- `roles.gestionar`

## Scoping por sucursal
- Toda operación operativa requiere una sucursal resuelta.
- El sistema acepta dos mecanismos válidos:
  - sesión web con `active_branch_id`
  - cabecera `X-Branch-Public-Id` para APIs stateful/integraciones controladas
- Si la sucursal no pertenece al usuario, el acceso se rechaza.
- Las policies usan `denyAsNotFound()` para reducir enumeración horizontal cuando corresponde.
- Los controladores y servicios validan además que empleados, clientes, órdenes, facturas y sesiones pertenezcan a la sucursal activa.

## Flujo POS actual
- Usuarios no admin entran directo a `Inicio de Cobro` tras autenticarse.
- Solo usan las dos pantallas operativas alineadas a Stitch.
- No se solicita cliente en el flujo principal.

## Reautenticación administrativa
- Aplica hoy para cierre sensible de caja y descuentos extraordinarios.
- Se almacena en sesión mediante:
  - `admin_reauthenticated_at`
  - `admin_reauthenticated_user_id`
  - `admin_reauthenticated_branch_id`
- La TTL se toma de `ConfiguracionSucursal.ventana_reautenticacion_minutos`.
- La reautenticación se invalida automáticamente cuando se cambia de sucursal o se cierra sesión.
- No se permite bypass entre usuarios ni entre sucursales.
- Los endpoints sensibles sin sesión segura reciben rechazo explícito.

## Flujo de acceso operativo
- La pantalla principal no expone correo electrónico como experiencia principal.
- Los perfiles visibles se derivan solo de usuarios activos con sucursales activas asignadas.
- Si existe registro de empleado asociado e inactivo, el perfil queda fuera del listado.
- La selección de perfil no autentica por sí sola: la contraseña sigue siendo obligatoria.
- Los intentos fallidos y exitosos del nuevo flujo quedan auditados.

## Eventos auditados hoy
- `auth.login`
- `auth.logout`
- `auth.login_failed`
- `auth.locked_out`
- `sucursal.activada`
- `clientes.creado`
- `clientes.actualizado`
- `agenda.cita_creada`
- `orden.creada`
- `pago.registrado`
- `factura.emitida`
- `caja.apertura`
- `caja.cierre`
- `seguridad.reautenticacion_admin_exitosa`
- `seguridad.reautenticacion_admin_fallida`

## Sanitización de auditoría
- El servicio de auditoría redacta claves sensibles como `password`, `token`, `secret`, `authorization`, `cvv` y `pan`.
- Los fallos de login guardan el `profile_public_id` seleccionado y nunca la contraseña.

## Riesgos mitigados
- Acceso horizontal a recursos de otra sucursal.
- Cierre de caja ajena por usuarios no gerenciales.
- Descuento extraordinario sin permiso o sin reautenticación vigente.
- Uso indebido de `X-Branch-Public-Id` fuera de sucursales asignadas.
- Reutilización de reautenticación entre sucursales o usuarios distintos.

## Pendientes de siguiente fase
- MFA para `super_admin`, `admin_negocio` y `gerente_sucursal`.
- Gestión formal de roles/permisos desde UI con auditoría dedicada.
- Políticas para anulación, devoluciones, reapertura de caja y exportaciones cuando se implementen esas features.
- Hardening adicional de encabezados HTTP y CSP productiva.

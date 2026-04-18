# API v1

## Convención general
- Prefijo: `/api/v1`
- Autenticación: `auth:sanctum`
- Aislamiento: middleware `active.branch`
- Resolución de sucursal:
  - sesión web con `active_branch_id`
  - o cabecera `X-Branch-Public-Id`
- Si no existe contexto válido de sucursal, la API responde `409`.
- Si una request stateful envía una cabecera de sucursal distinta a la sesión activa, la API responde `409`.
- Si el recurso pertenece a otra sucursal autorizada pero no visible por política, puede responder `404`.

## Nota sobre acceso al sistema
- El acceso principal de FERLEM NAILS ocurre por rutas web con sesión, no por API pública de login.
- El listado de perfiles visibles se resuelve server-side en la vista `GET /login` para reducir superficie innecesaria.
- La autenticación sigue siendo real y backend-side: perfil seleccionado + contraseña.

## Seguridad por tipo de endpoint
- `POST /auth/reautenticar-admin`: limitado por `throttle:sensitive-actions`
- `POST /ventas/ordenes/{orden}/pagos`: limitado por `throttle:sensitive-actions`
- `POST /ventas/ordenes/{orden}/factura`: limitado por `throttle:sensitive-actions`
- `POST /caja/sesiones`: limitado por `throttle:sensitive-actions`
- `POST /caja/sesiones/{sesionCaja}/cerrar`: requiere permiso + reautenticación + `throttle:sensitive-actions`

## Endpoints disponibles

### Autenticación
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/reautenticar-admin`

### Sucursales
- `GET /api/v1/sucursales`
- `POST /api/v1/sucursales/activar`

### Clientes
- `GET /api/v1/clientes`
- `POST /api/v1/clientes`
- `POST /api/v1/clientes/alta-rapida`
- `PUT /api/v1/clientes/{cliente}`

### Catálogo
- `GET /api/v1/catalogo/servicios`

### Agenda
- `GET /api/v1/agenda/citas`
- `POST /api/v1/agenda/citas`

### Empleados
- `GET /api/v1/empleados`

### POS y ventas
- `GET /api/v1/ventas/ordenes/{orden}`
- `POST /api/v1/ventas/ordenes`

### Pagos
- `POST /api/v1/ventas/ordenes/{orden}/pagos`

### Facturación
- `GET /api/v1/facturas`
- `GET /api/v1/facturas/{factura}`
- `POST /api/v1/ventas/ordenes/{orden}/factura`

### Caja
- `GET /api/v1/caja/sesion-activa`
- `POST /api/v1/caja/sesiones`
- `POST /api/v1/caja/sesiones/{sesionCaja}/cerrar`

### Reportes
- `GET /api/v1/reportes/resumen`

## Reglas críticas
- En el MVP operativo no admin, una orden puede existir sin cliente final.
- La operación, el pago y la factura quedan asociados al perfil autenticado mediante `user_id`.
- Ningún pago se registra si la sesión de caja no coincide con la sucursal de la orden.
- Ninguna factura se emite si la orden no está pagada o pertenece a otra sucursal.
- Ningún descuento extraordinario se acepta sin permiso y reautenticación vigente.
- Ninguna cabecera `X-Branch-Public-Id` fuera del alcance del usuario habilita acceso.

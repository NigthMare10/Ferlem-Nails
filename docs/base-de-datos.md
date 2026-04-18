# Base de datos

## Motor objetivo
MySQL es el motor objetivo del sistema. El proyecto queda preparado para ejecutarse localmente con SQLite, pero el diseño está pensado para MySQL en producción.

## Reglas generales
- Montos monetarios en enteros (centavos de HNL).
- `public_id` ULID en entidades expuestas.
- Claves foráneas explícitas.
- Soft deletes donde la trazabilidad histórica importa.

## Tablas principales
- `users`: usuarios internos autenticados.
- `sucursales`: sucursales operativas.
- `configuraciones_sucursal`: moneda, ISV y políticas por sucursal.
- `sucursal_user`: membresía de usuarios a sucursales.
- `clientes`: cliente maestro.
- `perfiles_cliente`: perfil operativo por sucursal.
- `empleados`: especialistas operativos.
- `empleado_sucursal`, `empleado_servicio`: pivotes operativos.
- `categorias_servicio`, `servicios`, `precio_servicios`, `historial_precios_servicio`.
- `citas`.
- `sesiones_caja`, `movimientos_caja`.
- `ordenes`, `detalle_ordenes`.
- `pagos`.
- `secuencias_documento`, `facturas`, `detalle_facturas`.
- `auditoria_eventos`.
- `personal_access_tokens`, `roles`, `permissions` y pivotes de Spatie.

## Relaciones críticas
- `clientes -> perfiles_cliente -> sucursales`
- `ordenes -> clientes`
- `ordenes -> pagos`
- `ordenes -> facturas`
- `facturas -> detalle_facturas`
- `sesiones_caja -> movimientos_caja`
- `sucursales -> secuencias_documento`

## Integridad aplicada
- No existe orden sin cliente (`cliente_id` obligatorio).
- No existe factura sin cliente ni sin orden.
- La factura solo puede emitirse desde una orden pagada.
- El cierre de caja solo puede ejecutarse sobre una sesión abierta.

## Índices relevantes
- `clientes(phone)`, `clientes(email)`
- `ordenes(sucursal_id, status, created_at)`
- `facturas(sucursal_id, number)` único
- `pagos(idempotency_key)` único
- `citas(sucursal_id, scheduled_start)`
- `auditoria_eventos(action)` y polimórfico auditable

## Suposiciones documentadas
- Se usa `ISV` 15% como base del MVP para Honduras.
- La numeración de factura se maneja por sucursal y tipo de documento.
- El cliente puede existir globalmente y tener perfil operativo por sucursal.

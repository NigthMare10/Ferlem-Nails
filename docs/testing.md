# Testing

## Estrategia actual
Se usa PHPUnit con `RefreshDatabase` y SQLite en memoria para validar acceso, cambio de perfil, navegación Stitch, logout y flujo operativo simplificado sin depender de infraestructura externa durante el ciclo rápido.

## Suites activas
- `Unit`: utilidades puras como formateo monetario.
- `Feature`: acceso, navegación admin, logout y flujo POS/factura.
- `Security`: verificaciones mínimas de acceso por perfil aún vigentes.

## Cobertura actual relevante
- Listado de perfiles visibles de acceso.
- Solo dos perfiles demo sembrados: `admin` y `prueba`.
- No mostrar perfiles inactivos o sin sucursal.
- Login por perfil + contraseña.
- Cambio de perfil real sin arrastrar la sesión anterior.
- Redirección inicial por rol y manejo de múltiples sucursales.
- Admin puede entrar a más de una pantalla real de Stitch.
- Logout vuelve al login.
- Flujo POS operativo sin cliente final.
- El perfil `prueba` puede pagar y ver la factura digital sin 403.
- Asociación de la operación al perfil autenticado.
- Login exitoso con asignación de sucursal activa.
- Auditoría de intento fallido de login.
- Restricción básica por roles en POS y reportes.
- Denegación de cambio a sucursal no asignada.

## Comando
```bash
tools/php/php.exe artisan test
```

## Estado actual
- 18 pruebas pasando.
- 65 aserciones pasando.

## Pendientes de siguiente fase
- Si se requieren nuevas pantallas o pasos, deben pedirse primero en Stitch/MCP.
- Si el negocio vuelve a requerir cliente final o caja operativa real, debe redefinirse la fase activa antes de reintroducir complejidad.

# Arquitectura de FERLEM NAILS

## Objetivo
Establecer una base real, modular, segura y mantenible para operar FERLEM NAILS con autenticación, sucursales, clientes, agenda, POS, caja, facturación y reportes básicos.

## Stack principal
- Backend: Laravel 13
- Autenticación first-party: sesiones web + Sanctum preparado para API stateful
- Frontend: Inertia + React + TypeScript + Vite
- Base de datos canónica: MySQL
- Ejecución local en este workspace: SQLite para pruebas rápidas y testing automatizado

## Organización de capas
- `app/Modules/*/Models`: entidades Eloquent por dominio
- `app/Modules/*/Services`: casos de uso y reglas de negocio
- `app/Modules/*/Http/Requests`: validación de entrada
- `app/Modules/*/Http/Controllers/Api/V1`: endpoints JSON versionados
- `app/Modules/*/Http/Controllers/Web`: páginas Inertia y acciones web
- `app/Modules/*/Http/Resources`: serialización consistente
- `app/Http/Middleware`: seguridad transversal, sucursal activa y reautenticación

## Principios aplicados
- No hay lógica crítica en controladores.
- El flujo POS exige cliente antes de cobro.
- Toda factura queda asociada obligatoriamente a cliente y orden.
- La sucursal activa se resuelve por sesión y se valida en middleware.
- Las acciones sensibles requieren reautenticación administrativa reciente.
- La autorización se refuerza por roles/permisos y por scoping de sucursal.

## Flujo operativo base
1. El usuario inicia sesión.
2. El sistema asigna o solicita sucursal activa.
3. La navegación interna queda limitada a esa sucursal.
4. El POS obliga a seleccionar cliente.
5. La orden calcula subtotal, impuesto, descuento y total en centavos.
6. El pago actualiza caja y marca la orden como pagada.
7. La facturación emite snapshot inmutable del detalle de la orden.

## Decisiones de escalabilidad
- Montos monetarios en enteros (centavos) para evitar errores de precisión.
- `public_id` ULID para exposición externa y route model binding seguro.
- API versionada en `routes/api/v1`.
- Sesiones, cache y jobs preparados desde la base.
- Reportes resumidos desacoplados en servicio específico.

## Ejecución local
- Se incluye `tools/php` y `tools/composer.phar` para poder trabajar sin depender de una instalación previa de PHP en la máquina.
- El proyecto compila frontend con `npm` y backend con el PHP portable local.

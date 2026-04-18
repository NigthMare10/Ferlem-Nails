# FERLEM NAILS

Fase minima activa basada unicamente en las 11 pantallas de Stitch.

## Qué incluye
- Autenticación operativa por selección de perfil + contraseña.
- Solo las 11 pantallas de Stitch como base visible del sistema.
- Flujo operativo simplificado sin cliente final.
- Pago simplificado sin caja abierta/cerrada ni captura real de tarjeta.
- Factura asociada al usuario autenticado.
- Dos perfiles demo: `admin` y `prueba`.

## Flujo operativo actual del MVP
- Para usuarios no admin, el flujo principal actual no usa cliente final.
- El contexto de la operación lo define el perfil autenticado del empleado.
- La secuencia operativa es:
  1. seleccionar perfil de empleado
  2. ingresar contraseña
  3. entrar a Inicio de Cobro
  4. elegir categoría
  5. pasar a Detalle de Cobro y Pago
  6. completar la operación
- El módulo de clientes existe, pero queda fuera del flujo operativo principal en esta fase.

## Pantallas activas de Stitch
1. Inicio de Cobro
2. Detalle de Cobro y Pago
3. Historial de Facturas
4. Detalle de Factura Digital
5. Detalle de Factura Premium
6. Cierre de Caja Diario
7. Lista de Empleados
8. Rendimiento por Empleado
9. Reportes de Ventas Analytics
10. Gestión de Empleados Admin
11. Ajuste de Precios Admin

## Pantallas nuevas detectadas en references
- Selección de Perfil
- Ingreso de Contraseña

Estas dos referencias conviven con el login operativo actual por selección de perfil + contraseña. La fase visible sigue girando alrededor del acceso y las 11 pantallas Stitch conectadas.

## Requisitos del entorno
- Node.js 24+
- El proyecto ya incluye PHP portable en `tools/php` y Composer local en `tools/composer.phar`.

## Instalación rápida
```bash
tools/php/php.exe tools/composer.phar install
npm install
copy .env.example .env
tools/php/php.exe artisan key:generate
tools/php/php.exe artisan migrate --seed
npm run build
```

## Configuración recomendada
- Edita `.env` para apuntar a MySQL en entornos reales.
- Define `SEED_ADMIN_PASSWORD` si quieres controlar la contraseña del usuario inicial.
- Define `SEED_STAFF_PASSWORD` si quieres fijar la contraseña del perfil demo `prueba`.
- Si no defines esas variables, el seeder genera contraseñas temporales y las muestra por consola.

## Perfiles demo de esta fase
- `admin`: perfil administrativo con acceso a todas las pantallas activas.
- `prueba`: perfil operativo simplificado que entra directo a `Inicio de Cobro`.

## Navegación actual
- `admin` puede navegar entre las pantallas Stitch administrativas desde los menús internos reescritos en frontend.
- `prueba` mantiene el flujo mínimo `Inicio de Cobro` -> `Detalle de Cobro y Pago` -> `Detalle de Factura Digital`.
- Los enlaces de `Salir` dentro de pantallas Stitch ahora vuelven al login de forma funcional.
- Cambiar de `admin` a `prueba` invalida primero la sesión previa antes de autenticar el nuevo perfil.

## Nuevo flujo de acceso
- La pantalla principal ya no pide correo.
- El personal selecciona su perfil operativo visible.
- Luego ingresa únicamente su contraseña.
- Si la autenticación es correcta, el sistema redirige automáticamente al módulo inicial según rol, permisos y sucursal.
- Si el usuario tiene varias sucursales sin una predeterminada, entra primero al selector de sucursal.

## Desarrollo
```bash
tools/php/php.exe artisan serve
npm run dev
```

## Pruebas
```bash
tools/php/php.exe artisan test
```

## Referencia visual original
El export de Stitch fue movido a `references/stitch-export` y se conserva solo como referencia de diseño.

## Documentación
- `docs/arquitectura.md`
- `docs/modulos.md`
- `docs/base-de-datos.md`
- `docs/api.md`
- `docs/seguridad.md`
- `docs/testing.md`
- `docs/frontend.md`
- `docs/pendientes-stitch.md`
- `docs/decisiones-tecnicas.md`

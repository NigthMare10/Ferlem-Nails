# Decisiones técnicas

## 2026-04-15 - Separar referencia visual y base real
El export de Stitch se movió a `references/stitch-export` y se mantuvo solo como insumo visual. La base real se construyó desde Laravel para no depender de HTML estático sin arquitectura.

## 2026-04-15 - PHP portable local
Se agregó `tools/php` y `tools/composer.phar` porque el entorno no contaba con PHP/Composer operativos inicialmente. Esto permite trabajar y ejecutar el proyecto en este workspace.

## 2026-04-15 - MySQL canónico con SQLite para ciclo rápido
La arquitectura y `.env.example` apuntan a MySQL. Para pruebas automatizadas y validación dentro del entorno actual se usa SQLite en memoria.

## 2026-04-15 - Inertia + React en lugar de reutilizar Stitch
Se eligió Inertia + React + TypeScript para el core operativo por ser más coherente con un sistema transaccional autenticado que Astro o HTML estático.

## 2026-04-15 - Cliente obligatorio antes del cobro
Se implementó el flujo funcional obligatorio: cliente -> servicios -> orden -> pago -> factura. No existe checkout válido sin cliente.

## 2026-04-15 - Roles y permisos con Spatie
Se adoptó Spatie Permission para RBAC porque ofrece madurez, middleware listos y buena integración con Laravel.

## 2026-04-15 - Configuración operativa absorbida por Sucursales
El módulo `Configuracion` queda reservado para una fase posterior. En esta base, la configuración crítica del MVP vive en `Sucursales` mediante `ConfiguracionSucursal` y `SecuenciaDocumento`.

## 2026-04-15 - APIs con sucursal por sesión o cabecera
Las APIs que dependen de sucursal aceptan sesión web con `active_branch_id` y, como respaldo controlado, la cabecera `X-Branch-Public-Id`.

## 2026-04-15 - Políticas de autorización por recurso
Se agregaron policies por módulo para evitar depender solo de middleware genérico y para responder con `404` en escenarios de acceso horizontal entre sucursales.

## 2026-04-15 - Roles canónicos del MVP
La matriz operativa canónica del MVP quedó definida con `super_admin`, `admin_negocio`, `gerente_sucursal`, `cajero`, `tecnica`, `recepcionista` y `auditor`. Se mantienen alias legacy (`administrador`, `gerencia`) únicamente como compatibilidad temporal.

## 2026-04-15 - Reautenticación ligada a usuario y sucursal
La confirmación administrativa ya no se valida solo por timestamp. Ahora queda amarrada a usuario, sucursal y TTL configurado por sucursal, y se invalida al cambiar de sucursal o cerrar sesión.

## 2026-04-15 - Hardening de caja y descuentos extraordinarios
Cerrar caja ajena requiere permiso explícito. Los descuentos fuera de política requieren permiso específico y reautenticación vigente, incluso si el request intenta forzar el payload.

## 2026-04-15 - Acceso operativo por selección de perfil
Se reemplazó el login principal por correo+contraseña por una experiencia interna de terminal: primero se selecciona un perfil visible del personal y luego se valida la contraseña. El backend sigue autenticando contra el usuario real y no se debilitó la seguridad.

## 2026-04-15 - Resolución de sucursal posterior al acceso
Si el usuario tiene una sola sucursal activa o una predeterminada, entra directo. Si tiene varias y ninguna default, el sistema lo envía al selector de sucursal tras autenticarse.

## 2026-04-15 - Flujo POS MVP sin cliente final
Para respetar Stitch y evitar pasos ajenos al diseño operativo, el flujo principal no admin dejó de exigir cliente final. La operación queda asociada al perfil autenticado del empleado y el módulo de clientes permanece fuera del recorrido principal en esta fase.

## 2026-04-16 - Reinicio conceptual hacia una fase minima basada en Stitch
La app activa se simplificó deliberadamente para que gire solo alrededor de las 11 pantallas ya existentes en Stitch. Se desactivaron del flujo visible las capas empresariales que no son necesarias en esta fase.

## 2026-04-16 - Solo dos perfiles demo
La semilla activa de esta fase deja solo dos perfiles utilizables: `admin` y `prueba`. El objetivo es reducir ruido, facilitar validación y probar únicamente el recorrido simplificado.

## 2026-04-16 - Pantallas Stitch montadas con frame frontend navegable
Para evitar backend nuevo en la iteración de references, las pantallas administrativas de Stitch se mantuvieron sobre el controlador existente pero la navegación, correcciones de texto, logout simple y scroll se resolvieron desde `resources/js/Pages/Stitch/Frame.tsx`.

## 2026-04-16 - Traducción y lempiras en frontend embebido
La normalización de marca, idioma y moneda de las pantallas embebidas se reforzó del lado frontend con reemplazos de texto y rutas reescritas para que el comportamiento visible en navegador sea coherente con FERLEM NAILS y HNL.

## 2026-04-16 - Eliminación del recálculo dinámico de altura en pantallas Stitch
El ajuste de altura por `postMessage` y listeners de resize generaba crecimiento infinito de scroll y estados de carga anómalos. Se eliminó esa estrategia y se dejó el iframe estable a viewport completo con scroll interno de cada pantalla.

## 2026-04-16 - Reautenticación limpia al cambiar de perfil
El `POST /login` ya no depende del middleware `guest`; ahora invalida una sesión previa autenticada antes de autenticar el perfil nuevo. Esto corrige el bug donde al volver atrás y elegir otro perfil se mantenía el usuario anterior.

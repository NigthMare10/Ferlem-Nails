# Plan final de estabilizacion de Studio Lemus

Fecha de analisis: 2026-07-25  
Estado: En pruebas / No  
Alcance de esta intervencion: estabilizacion A-D implementada; pendiente de validacion manual

## Resultado tecnico de estabilizacion (2026-07-25)

- Bloque A: `SaleMobileCheckout` es el unico shell movil para venta normal y cita. Elimina la regla que ocultaba `Ver resumen` bajo 420 px, conserva carrito al cerrar, usa bottom sheet, mide su altura para reservar contenido y aplica safe areas. El viewport declara `viewport-fit=cover` e `interactive-widget=resizes-content`.
- Bloque B: `GET /notifications` ahora es exclusivamente Inertia. Feed, lectura individual y masiva son JSON consumido por `fetch` sin `X-Inertia`; el polling visible usa 60 segundos y no hace recarga completa. El contador viene del servidor despues de cada lectura.
- Bloque C: migracion aditiva `2026_07_25_130000_add_cancellation_fields_to_sales_table.php`, permiso `sales.cancel` solo para owner por defecto, `CancelSaleAction` con bloqueo/transaccion, comprobante `ANULADA`, evento de cita y notificacion post-commit. No hay borrado ni reapertura de citas.
- Bloque D: filtros de query string para `date`, `month`, `date_from`, `date_to`, empleado, metodo y modo. Mes usa selector `YYYY-MM`; el desglose diario incluye dias cero y las anuladas quedan fuera de los resultados reales, apareciendo solo como conteo/monto secundario.
- Verificacion: el comando `php artisan test` con PHP predeterminado 8.2.12 falla antes de iniciar porque Composer exige >=8.3. Con PHP 8.3 de Laragon: `optimize:clear`, migracion batch 2, seeders, rutas y `migrate:status` pasaron; la suite paso 257 pruebas/2,297 aserciones; Pint, typecheck, build y `git diff --check` pasaron. SQLite estuvo disponible bajo PHP 8.3, por lo que no se uso configuracion temporal. Produccion se desplego por SSH el 2026-07-25 y el smoke test HTTPS paso; Hostinger mantiene `exec()` deshabilitado, por lo que el enlace de storage se restauro con `ln -s` desde SSH.
- Riesgo manual pendiente: no existe navegador automatizado; validar 1440x900, 1024x768, 768x1024, 390x844, 360x800 y 320x700, Safari iPhone, Chrome Android, WebView WhatsApp, teclado y landscape.

## Modulo Facturas - Prompt 1 de 2

**Estado:** En pruebas / No. **Despliegue:** No realizado; pendiente de validacion manual del usuario.

- Se agrego `Facturas` al sidebar entre Nueva venta y Agenda, visible por permiso efectivo y protegido en backend mediante alcance `sales.view_all|sales.view_own`.
- No se creo una entidad Invoice. Listado, detalle, anulacion, impresion y capturas usan `Sale`, `SaleItem` y `SalePayment` existentes.
- Nueva migracion reversible `2026_07_25_150000_add_client_name_to_sales_table.php`: snapshot nullable, backfill desde citas vinculadas y null seguro para ventas directas historicas.
- Rutas nuevas: index/show, cancelacion reutilizada y POST/GET anidado de captura. El recibo termico existente permanece como unica implementacion de impresion.
- Filtros backend: numero/clienta, desde/hasta Honduras, completed/canceled, cash/card/transfer/mixed, empleado autorizado y captura con/pending; paginacion 20 y orden reciente descendente.
- Interfaz: tabla desktop, cards moviles, detalle por secciones, dialogo de anulacion y dialogo persistente de upload con vista previa. Ninguna accion sensible depende solo de Vue.
- Carga posterior: una unica captura sobre pago transfer completed sin prueba previa; archivo privado aleatorio, MIME real, maximo 5 MB, rollback de archivo ante fallo y sin reemplazo en este prompt.
- Permisos nuevos: `sales.view_all` y `sales.upload_transfer_proof`; owner todos, administrator all/upload/view proof sin cancel por defecto, employee upload solo bajo scope propio. Los seeders siguen idempotentes.
- Seguridad: `SaleAccess` unifica listado, detalle, receipt y proof; pertenencia pago-venta obligatoria; no hay DELETE, rutas fisicas, symlink publico, costos internos ni datos sensibles.
- Notificacion posterior deduplicada para responsables autorizados, sin adjuntar imagen.
- Verificacion automatica: 273 pruebas y 2,520 aserciones pasan con PHP 8.3. Pint, typecheck, build y `git diff --check` pasan; permanece la advertencia no bloqueante por bundle mayor de 500 kB.
- Pendiente manual: roles y URLs ajenas, filtros/paginacion, snapshot, Mixto, anulacion, carga/consulta privada, recibo y responsive. No se desplego en Hostinger.

## Modulo Facturas - Prompt 2 de 2 (2026-07-26)

**Estado:** En pruebas / No. **Despliegue:** Realizado; requiere validacion manual autenticada.

- `AppLayout` ahora muestra Facturas mediante `canAny(['sales.view_own', 'sales.view_all'])` del composable existente, no mediante rol ni un booleano de navegacion derivado. Esta fue la causa de que el item pudiera no aparecer con acceso efectivo.
- Orden final: Inicio, Nueva venta, Facturas, Agenda, Historial de citas, Ganancias Generales y Configuracion, cada uno bajo su permiso existente. Facturas usa `mdi-file-document-outline` y `/invoices`.
- El estado activo usa prefijo `/invoices`, por lo que persiste en listado, filtros, paginacion, detalle, anulacion y captura. Nueva venta no se activa dentro de Facturas.
- El drawer temporal movil usa el mismo item; cierra antes de navegar. No se agrego otro drawer, overlay ni CSS que introduzca scroll horizontal.
- Inicio agrega `Ver facturas` solo con `sales.view_own|sales.view_all`; no hay metricas ni datos simulados nuevos.
- `auth.permissions` sigue llegando desde `AppServiceProvider`; `usePermissions` consume ese arreglo. El seeder local idempotente confirma owner con own/all/reprint/cancel/upload/view proof; administrator con own/all/reprint/upload/view proof y sin cancel; employee con own/reprint/upload y sin all/cancel.
- Validacion local: `optimize:clear`, `db:seed` y `route:list` correctos; Invoice 10/182; suite 274/2,567; Pint, typecheck y `git diff --check` correctos. Build correcto; advertencia no bloqueante por bundle mayor de 500 kB.
- Hostinger: release precompilado por SSH, `.env` y `storage` preservados, sin `db:seed`. Solo se aplico `2026_07_25_150000_add_client_name_to_sales_table` porque estaba pendiente; caches config/route/view regenerados y manifest actual confirmado en `public_html/build`. Se corrigio el `index.php` del document root separado para apuntar a `../studio-lemus` despues de la sincronizacion de public.
- URL publica: `https://violet-crow-104407.hostingersite.com/invoices` redirige a login correctamente; `/login` responde 200. Queda validar mediante sesiones reales que owner lista todas, employee solo propias, sidebar/drawer y ausencia de errores de consola.

## 0. Fuentes y estado real inspeccionado

Se leyeron completamente:

- `docs/STUDIO_LEMUS_IMPLEMENTATION_PLAN.md`.
- `docs/STUDIO_LEMUS_APPOINTMENTS_PLAN.md`.
- `docs/STUDIO_LEMUS_PRODUCT_ROADMAP.md`.

Tambien se inspeccionaron las rutas, migraciones, modelos, acciones, controladores, Resources, Vue, tipos y pruebas relacionados con ventas, citas, notificaciones y Ganancias Generales.

Estado real relevante:

- Venta normal y venta desde cita comparten `resources/js/Pages/Sales/Create.vue`, `SaleLineItem`, `SalePaymentMethod`, `SaleCheckoutSummary`, `ConfirmSaleDialog` y el escritor backend `PersistCompletedSaleAction`.
- No comparten el contenedor movil: la barra y el bottom sheet actuales existen solo para la venta normal.
- Las notificaciones tienen ownership backend correcto, deduplicacion por destinatario/hecho y polling de 60 segundos, pero mezclan solicitudes Inertia con respuestas JSON.
- `sales.status` solo admite `completed`; no existen `canceled_at`, `canceled_by` ni `cancellation_reason`.
- `sale_items` y `sale_payments` son inmutables y no se eliminan fisicamente.
- `sales.appointment_id` es nullable y unico. Una cita completada mantiene una sola venta vinculada.
- Ganancias Generales ya soporta `today`, `week`, `month` y `custom` en backend. La experiencia parece diaria por la UI, no porque el rango mensual este roto.
- El reporte real ya filtra `sales.status = completed`, por lo que una futura venta `canceled` quedara fuera de los agregados principales.
- Antes de este documento el worktree ya contenia cambios no relacionados en `package.json` y `package-lock.json`. No forman parte de este plan y no deben alterarse ni revertirse durante la implementacion.

## 1. Causa probable del fallo movil

Hay dos causas directas y una serie de riesgos secundarios.

### 1.1 `Ver resumen` se oculta expresamente en telefonos estrechos

En `resources/js/Pages/Sales/Create.vue:415-425`, la media query `@media (max-width: 420px)` cambia la barra a una columna y aplica:

```css
.mobile-checkout-bar__summary {
    display: none;
}
```

Por tanto, en cualquier viewport de 420 px o menos el acceso `Ver resumen` desaparece por diseño. No depende de una marca concreta de telefono; depende del ancho CSS disponible. Esto explica los equipos que muestran solo `Cobrar`.

### 1.2 El flujo desde cita esta excluido de la barra

En `resources/js/Pages/Sales/Create.vue`:

- La clase que agrega espacio inferior depende de `cart.length` en la linea 209.
- La barra usa `v-if="!appointment && smAndDown && cart.length"` en la linea 323.
- El bottom sheet usa `v-if="!appointment"` en la linea 335.
- La venta normal usa `cart`, `totalServices`, `totalCents` y `openConfirmation`.
- La venta desde cita usa otro estado: `appointmentCart`, `appointmentTotalServices`, `appointmentBalanceCents` y `openAppointmentConfirmation`.

Quitar solamente `!appointment` no arreglaria el problema: la barra seguiria consultando el carrito y los totales equivocados. En cita, el resumen esta al final de una columna larga (`Create.vue:246-270`) y no existe acceso persistente movil.

### 1.3 Riesgos CSS y viewport existentes

- Vuetify usa `smAndDown`; con los thresholds instalados el cambio ocurre debajo de 840 px. No hay thresholds personalizados en `resources/js/app.ts`.
- Venta normal cambia a resumen lateral desde 840 px, pero la cita usa columnas `lg`, por lo que permanece en una columna hasta aproximadamente 1145 px.
- La barra usa `position: fixed`, `bottom: 0` y `z-index: 5` en `Create.vue:381-395`.
- El `z-index` no explica la ausencia actual: el elemento no se renderiza en cita y se oculta por CSS bajo 420 px. Debe permanecer debajo de dialogs y bottom sheets de Vuetify.
- La barra agrega `env(safe-area-inset-bottom)`, pero `resources/views/app.blade.php:5` no declara `viewport-fit=cover`.
- El padding de pagina es fijo: 82 px y 70 px. No se deriva de la altura real de la barra y no incorpora correctamente todas las variantes de safe area.
- `SaleCart.vue:75-77` usa `max-height: min(48dvh, 470px)` y scroll interno. El bottom sheet usa `88dvh`; esto es mejor que `vh`, pero falta un fallback y una estrategia comun para navegadores embebidos.
- No hay manejo especifico de `VisualViewport`, teclado virtual, altura reducida ni orientacion horizontal.
- En landscape, un telefono ancho puede cruzar el breakpoint y recibir una composicion de escritorio pese a tener poca altura.
- El numero de servicios no cambia la condicion basica: con uno o varios items la barra normal aparece, pero los textos pueden envolver y modificar la altura. En cita no aparece con ninguna cantidad.

## 2. Componentes y estilos involucrados

### Archivos actuales directos

- `resources/js/Pages/Sales/Create.vue`: dos estados de carrito, bifurcacion normal/cita, barra fija, bottom sheet, resumen sticky y confirmacion.
- `resources/js/Components/Sales/SaleCart.vue`: resumen de venta normal, lineas, metodo, totales y CTA.
- `resources/js/Components/Sales/SaleLineItem.vue`: linea compartida por ambos flujos.
- `resources/js/Components/Sales/SalePaymentMethod.vue`: metodo compartido.
- `resources/js/Components/Sales/SaleCheckoutSummary.vue`: bruto, fee, neto, adelanto y saldo.
- `resources/js/Components/Sales/ConfirmSaleDialog.vue`: confirmacion compartida y fullscreen movil.
- `resources/js/Components/Sales/ServiceCard.vue`: selector de servicios.
- `resources/js/types/sales.ts`: contratos de ambos carritos.
- `resources/views/app.blade.php`: viewport sin `viewport-fit=cover`.
- `resources/css/app.css`: alturas `100dvh`, breakpoints globales y layout principal.
- `tests/Feature/SaleCheckoutStructureTest.php`: hoy solo comprueba piezas internas compartidas; no comprueba barra, safe area ni comportamiento responsive.

### Componentes futuros recomendados

- Nuevo `resources/js/Components/Sales/SaleMobileCheckout.vue`: unico shell de barra fija y resumen movil para venta normal y cita.
- Opcionalmente, si el contenido queda demasiado grande en `Create.vue`, un componente presentacional `SaleCheckoutReview.vue` para componer lineas, metodo, resumen, alertas y CTA sin duplicar reglas financieras.

No se recomienda duplicar una segunda barra para citas ni copiar el markup del resumen. Una sola instancia debe recibir el contexto activo.

## 3. Solucion responsive recomendada

### 3.1 Estado unificado

`Sales/Create.vue` debe derivar un contrato comun:

- `activeItems`: `appointmentCart` o `cart`.
- `activeServicesCount`: cantidad de servicios del flujo activo.
- `activeChargeCents`: saldo de cita o total normal.
- `activeProcessing`: `form.processing`.
- `activeCheckoutDisabled`: procesamiento, carrito vacio y, en cita, adelanto superior al trabajo.
- `openActiveConfirmation`: delega en `openAppointmentConfirmation` u `openConfirmation`.
- `showMobileCheckout`: viewport movil y carrito activo con al menos un servicio.

La misma condicion debe gobernar barra y compensacion inferior. No debe consultarse `cart.length` cuando el flujo activo usa `appointmentCart`.

### 3.2 Un unico componente movil

`SaleMobileCheckout.vue` debe:

- Renderizarse siempre que `showMobileCheckout` sea verdadero.
- Mostrar siempre cantidad, accion `Ver resumen` y boton `Cobrar` o `Completar y cobrar`.
- No ocultar `Ver resumen` en ningun breakpoint.
- En 320-420 px, usar dos filas si hace falta: resumen arriba y CTA abajo, ambos visibles.
- Abrir un `VBottomSheet` en portrait con altura disponible y scroll interno unico.
- Usar dialogo fullscreen en movil de poca altura o landscape cuando el bottom sheet no sea usable.
- Emitir cierre sin mutar `cart`, `appointmentCart`, metodo, asignaciones o tokens.
- Emitir cobro al mismo dialogo de confirmacion que ya usa cada flujo.

Cerrar el resumen solo cambia su estado abierto/cerrado. No navega, no reinicia formularios y no pierde el carrito.

### 3.3 Safe area y contenido no cubierto

- Agregar `viewport-fit=cover` al meta viewport.
- Evaluar `interactive-widget=resizes-content` para Chrome Android/WebViews, verificando compatibilidad antes de dejarlo definitivo.
- Barra: padding inferior `calc(12px + env(safe-area-inset-bottom, 0px))` y padding lateral con `safe-area-inset-left/right`.
- Sheet/dialog: acciones inferiores con el mismo inset.
- Usar `100dvh` para alto moderno y fallback `100vh` antes de la declaracion moderna.
- Medir la altura real de la barra con `ResizeObserver` dentro del componente y exponer una variable CSS, por ejemplo `--sale-mobile-bar-height`.
- La pagina debe usar `padding-bottom: calc(var(--sale-mobile-bar-height, 88px) + 16px)` mientras la barra exista.
- No usar offsets por modelo de telefono ni deteccion por user agent.

### 3.4 Teclado, navegadores y orientacion

- Probar Chrome Android, Safari iPhone y navegador embebido de WhatsApp.
- Mantener la barra dentro del viewport visual cuando aparece el teclado, sin tapar buscador, resultados ni acciones del resumen.
- El contenido del sheet/fullscreen debe tener un solo scroll interno y acciones alcanzables con teclado abierto.
- En landscape se debe decidir por ancho y altura util, no asumir que un ancho mayor equivale a desktop.
- No elevar la barra por encima de `VDialog`/`VBottomSheet`; la capa de confirmacion debe seguir dominando.

### 3.5 Criterios de aceptacion

- Cero servicios: barra ausente.
- Uno o varios servicios: barra presente.
- `Ver resumen` y `Cobrar` siempre visibles.
- Venta normal y cita montan el mismo componente movil.
- El ultimo contenido de la pagina nunca queda debajo de la barra.
- Home indicator, barra de Android y chrome del navegador no cubren acciones.
- Cerrar resumen conserva carrito, metodo, ejecutores, servicios retirados y adelanto.

## 4. Causa exacta del error Inertia

El error es un incumplimiento de protocolo, no un problema de formato, CSRF u ownership.

### Flujo actual

- `NotificationBell.vue:31-34` usa `router.patch` para lectura individual.
- `Notifications/Index.vue:48-54` usa `router.patch` para lectura individual.
- `Notifications/Index.vue:60-63` usa `router.patch` para lectura masiva.
- Una llamada `router.patch` envia una solicitud Inertia con `X-Inertia: true` y espera una Inertia page o un redirect compatible.
- `NotificationController::read()` en las lineas 44-52 devuelve `JsonResponse` con una notificacion individual.
- `NotificationController::readAll()` en las lineas 54-59 devuelve `JsonResponse` con `unread_count`.

Inertia recibe JSON plano sin una Inertia page y muestra:

> All Inertia requests must receive a valid Inertia response, however a plain JSON response was received.

La base ya fue actualizada antes de que el cliente rechace la respuesta. Por eso una fila puede quedar leida aunque no ocurra `onSuccess`, no se abra la entidad y el contador quede obsoleto.

### Otros hallazgos

- `GET /notifications` es hoy un endpoint dual: Inertia o JSON segun `expectsJson()` (`NotificationController.php:16-35`). Debe tener un solo contrato.
- `GET /notifications/recent` devuelve JSON, pero la campana no lo usa.
- El polling usa `router.reload({ only: ['auth'] })` cada 60 segundos (`NotificationBell.vue:37-55`), por lo que mezcla polling con una recarga parcial Inertia de la pagina actual.
- El polling ya evita pestaña oculta y peticiones solapadas; esas reglas deben conservarse.
- `Ver todas` usa `href="/notifications"` (`NotificationBell.vue:107`) y puede hacer una navegacion completa.
- Ownership actual es correcto: `NotificationController::owned()` restringe por `notifiable_type = User` y `notifiable_id = usuario actual`.
- Las pruebas usan `patchJson`, pero el frontend usa `router.patch`; por eso no reproducen el contrato fallido.

## 5. Contrato final de notificaciones

### 5.1 Navegaciones Inertia

Operaciones:

- Abrir `GET /notifications`.
- Filtrar y paginar la pagina.
- Abrir la URL interna de una entidad despues de marcarla.
- Volver o redirigir despues de una operacion que se decida implementar como formulario Inertia.

Respuestas validas:

- Inertia page.
- Redirect HTTP 303.
- `back(303)` con flash.

`GET /notifications` debe ser exclusivamente una pagina Inertia. Debe eliminarse su rama `expectsJson()`.

### 5.2 Consultas y mutaciones JSON internas

Operaciones:

- Obtener conteo no leido y ultimas notificaciones.
- Marcar una como leida.
- Marcar todas como leidas.

Cliente:

- `fetch` o `axios` con `Accept: application/json`, cookies de sesion, CSRF en mutaciones y sin `X-Inertia`.

Servidor:

- JSON consistente.
- Errores 401/403/404/419 tambien JSON.
- Ninguna Inertia page para estos endpoints.

### 5.3 Snapshot para campana y polling

Conservar una ruta JSON, preferiblemente la actual `GET /notifications/recent`, con respuesta:

```text
data.unread_count
data.recent[]
data.as_of
```

Conteo y recientes deben llegar juntos. El frontend reemplaza el contador por el valor autoritativo; no hace decrementos ciegos.

### 5.4 Lectura individual

`PATCH /notifications/{notification}/read` mediante `fetch` debe devolver:

```text
data.notification
data.unread_count
data.changed
```

Reglas:

- Idempotente: una segunda lectura devuelve `changed = false`.
- Query siempre acotada al destinatario actual; una notificacion ajena responde 404.
- Bloqueo local por ID para evitar doble click.
- Tras exito, actualizar campana/lista y ejecutar una sola `router.visit(notification.url)`.
- Una notificacion ya leida navega directamente sin PATCH.
- La URL debe ser interna: comenzar con `/`, no con `//` y sin esquema externo. Fallback a `/notifications`.

### 5.5 Lectura masiva

`PATCH /notifications/read-all` mediante `fetch` debe devolver:

```text
data.updated_count
data.unread_count
data.as_of
```

El conteo debe consultarse despues del `UPDATE`; no codificarse de forma ciega. Solo actualiza filas del usuario autenticado.

En pagina `unread`, la lista visible se vacia o se hace `router.reload({ only: ['notifications'] })` despues de la mutacion JSON. Esa recarga parcial si es una navegacion Inertia valida.

### 5.6 Estado frontend y polling visible

Centralizar el estado en un composable pequeno, por ejemplo `useNotifications.ts`, compartido por campana e indice:

- `unreadCount`.
- `recent` deduplicadas por ID.
- IDs en proceso de lectura.
- lectura masiva en proceso.
- request de polling en curso.
- ultimo instante sincronizado.

Polling:

- Consulta JSON inmediata al montar y luego cada 60 segundos.
- Solo con `document.visibilityState === 'visible'`.
- Al volver visible, consulta inmediata y reinicia temporizador.
- Un solo request simultaneo.
- `AbortController` al desmontar.
- `setTimeout` recursivo despues de finalizar, no intervalos acumulables.
- Un fallo transitorio no recarga la pagina ni duplica registros.
- Recientes se reconcilian por ID, se ordenan y se limitan a 10.

## 6. Modelo de anulacion

### 6.1 Estado actual y campos faltantes

`database/migrations/2026_07_19_130000_create_sales_table.php:19` define:

```text
status enum('completed') default 'completed'
```

`app/Models/Sale.php` solo define `STATUS_COMPLETED`. No existen campos de anulacion.

### 6.2 Esquema propuesto para implementar tras aprobacion

Una migracion futura, aditiva y reversible, debe:

- Ampliar `status` a `completed|canceled`.
- Agregar `canceled_at` timestamp nullable.
- Agregar `canceled_by` FK nullable a `users`, con `restrictOnDelete`.
- Agregar `cancellation_reason` text nullable.
- Conservar todas las ventas existentes como `completed`.
- Conservar el indice existente `(status, sold_at)`.
- Agregar un indice por `canceled_at` solo si la consulta secundaria y `EXPLAIN` lo justifican.

No se necesita una tabla de borrados, soft deletes, lineas negativas ni una segunda venta de reversa para este alcance. Los cuatro campos solicitados en `sales` son suficientes para una unica transicion auditada.

### 6.3 Reglas de dominio

- Solo una venta `completed` puede anularse.
- Motivo obligatorio, normalizado, entre 10 y 500 caracteres.
- Actor y hora provienen del servidor.
- Accion dentro de `DB::transaction` con `lockForUpdate` sobre la venta.
- Segunda anulacion, concurrente o posterior, se rechaza claramente.
- Unica transicion permitida: `completed -> canceled`.
- No existe `canceled -> completed`.
- No se cambia ni reutiliza `sale_number`.
- No se cambia `sold_at`, vendedor, totales, fee, neto, cita o token.
- No se eliminan ni editan `sale_items`.
- No se eliminan ni editan `sale_payments`.
- La venta sigue disponible por su URL y para auditoria.
- La operacion no afirma ni ejecuta un reembolso bancario o en efectivo. Una devolucion real futura necesita su propio modelo auditado.

### 6.4 Accion y respuesta

Crear una accion `CancelSaleAction` que:

1. Autorice usuario activo y `sales.cancel`.
2. Bloquee la venta.
3. Revalide estado `completed`.
4. Si hay cita vinculada, bloquee y valide la relacion historica.
5. Guarde `status`, `canceled_at`, `canceled_by` y motivo en una unica actualizacion controlada.
6. Agregue evento de cita si corresponde.
7. Publique la notificacion interna dentro de la misma transaccion.
8. Devuelva la venta recargada.

La ruta futura debe ser un POST web con CSRF y respuesta redirect 303 al comprobante con flash. No debe devolver JSON a un `router.post` Inertia.

### 6.5 Inmutabilidad

`Sale.php` hoy solo protege el numero y el borrado. La implementacion debe impedir que una venta persistida cambie campos financieros o historicos fuera de la accion de anulacion. Debe protegerse expresamente la imposibilidad de reactivacion.

## 7. Permisos propuestos

Crear exactamente:

```text
sales.cancel
```

Asignacion por defecto:

| Rol | Asignacion |
|---|---|
| Owner | Si, persistida en el rol ademas del bypass de Gate |
| Administrator | No por defecto; solo concesion explicita |
| Employee | No por defecto |

Reglas:

- Middleware, Form Request y Action validan permiso; ocultar el boton no autoriza.
- Un administrator con concesion directa puede anular.
- El permiso debe permitirle abrir el contexto minimo del comprobante que va a anular, aunque no se agregue un historial general de ventas en esta fase.
- `notifications.access` no concede anulacion.
- `sales.reprint` no concede anulacion.

## 8. Impacto en citas vinculadas

Decision mas segura:

- No reabrir automaticamente la cita.
- Mantener `appointments.status = completed`.
- Mantener `completed_at` y el evento `completed`.
- Mantener `sales.appointment_id`.
- Mantener la restriccion de una venta por cita.
- No generar otra venta automaticamente.
- No devolver el adelanto a `pending`.
- Mantener `appointment_deposits.status = applied`, `applied_amount`, resolucion y `sale_payment` de adelanto.
- Agregar un evento append-only `sale_canceled` al historial de la cita, con numero de venta, actor, hora y motivo seguro.
- Mostrar la venta vinculada como `Anulada` en Historial y detalle.
- La cita sigue sin poder usar `Atender y cobrar`.

Justificacion:

- La cita completada registra que el servicio ocurrio; anular el cobro no borra ese hecho operativo.
- Reabrirla la devolveria a agenda/proyeccion y permitiria un cobro duplicado.
- Reabrir el adelanto permitiria reutilizar dinero ya registrado en un pago inmutable.
- La FK unica de cita impide una venta de reemplazo. Un flujo futuro debera decidir explicitamente si una nueva venta puede referenciar a la anulada o si se habilita una relacion de reemplazo; queda fuera de esta estabilizacion.

## 9. Impacto en comprobantes

El comprobante existente en `resources/js/Pages/Sales/Receipt.vue` debe continuar mostrando snapshots y pagos originales.

Cambios futuros:

- `SaleReceiptResource` expone `status`, `is_canceled`, `canceled_at_display`, actor y motivo.
- Banner visible `ANULADA` en pantalla y en `@media print`.
- Mostrar actor, fecha Honduras y motivo.
- Mantener numero, fecha original, servicios, ejecutores, pagos y total.
- No cambiar el total a cero ni agregar lineas negativas.
- Boton `Anular venta` solo para venta `completed` y usuario autorizado.
- Despues de anular, retirar definitivamente la accion.
- La reimpresion conserva la marca `ANULADA`.
- La notificacion de anulacion enlaza al mismo comprobante historico.

## 10. Impacto en Ganancias Generales

### 10.1 Exclusion principal

`BuildSalesSummaryAction.php:117-137` ya filtra `Sale::STATUS_COMPLETED` tanto en cabeceras como en items. Al introducir `STATUS_CANCELED`, las anuladas quedan automaticamente fuera de:

- Ingresos brutos.
- Comision POS.
- Ingreso neto posterior a POS.
- Ventas completadas.
- Servicios realizados.
- Promedio por venta.
- Rendimiento por empleado.
- Desglose diario principal.

Este filtro debe preservarse en toda consulta nueva. No debe depender de ocultamiento frontend.

### 10.2 Datos secundarios de anulacion

Pueden agregarse, separados y subordinados:

- Cantidad de ventas anuladas.
- Monto original anulado.

Para que el reporte del periodo reconcilie las ventas originadas en ese periodo, estas metricas deben filtrar ventas `canceled` por `sold_at` dentro del mismo rango. No deben restarse de los totales principales porque ya estan excluidas; hacerlo descontaria dos veces.

Un reporte futuro de actividad de anulaciones por `canceled_at` es otro concepto y debe rotularse como tal.

### 10.3 Terminologia

- `gross_revenue`: Ingresos brutos.
- `pos_fee`: Comision POS.
- `net_income`: Ingreso neto posterior a POS.
- Nunca llamar `ganancia neta` a `net_amount`.
- Mantener la aclaracion de que no se han descontado otros costos o gastos.

## 11. Diseno final de periodos

### 11.1 Diagnostico actual

El backend ya funciona por periodo:

- `SalesEarningsRequest.php:43` valida `today|week|month|custom`.
- `ReportPeriod.php:21-33` calcula dia, semana lunes-domingo, mes completo y rango personalizado.
- Los limites locales se convierten a UTC en `BuildSalesSummaryAction` y `BuildAppointmentProjectionAction`.
- `Phase3BEarningsTest.php:121-134` ya comprueba que un mes usa primero y ultimo dia local.

La UI parece limitada a un dia porque:

- El valor por defecto es `today`.
- Cambiar selector no aplica hasta pulsar `Aplicar`.
- Mes usa un input `type="date"` rotulado `Fecha de referencia` (`Earnings/Index.vue:131-133`).
- El desglose diario solo contiene dias con ventas.
- La tabla usa `hide-default-footer` sin declarar que muestre todas las filas.
- En modo solo proyeccion no existe desglose diario real.

### 11.2 Query string final

Contrato recomendado:

```text
period=today&date=YYYY-MM-DD
period=week&date=YYYY-MM-DD
period=month&month=YYYY-MM
period=custom&date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
employee_id=<id opcional>
payment_method=cash|card|vacio
mode=actual|projection|both
```

Reglas:

- `date` es el dia de referencia para Hoy y Semana.
- `month` es selector mensual explicito.
- Durante una transicion compatible se puede aceptar `period=month&date=YYYY-MM-DD`, normalizandolo al mes; la respuesta debe devolver `month`.
- Custom exige Desde/Hasta inclusivos y maximo 366 dias.
- Campos no relevantes deben limpiarse al construir la URL.
- `payment_method` afecta solo resultados reales, no proyeccion.
- `employee_id` filtra por ejecutor real y por asignado futuro, como hoy.

### 11.3 Controles de pantalla

- Atajos visibles: Hoy, Esta semana, Este mes, Personalizado.
- Hoy/Semana: selector `Fecha de referencia`.
- Mes: `VTextField type="month"` rotulado `Mes`.
- Personalizado: Desde/Hasta.
- Empleado: todos o una persona.
- Metodo: Todos, Efectivo, Tarjeta; explicar que una venta con ambos metodos coincide con cualquiera y se incluye completa bajo el contrato actual.
- Vista: Resultados reales, Proyeccion, Ambos, segun permiso.
- Mostrar siempre etiqueta y rango efectivo, por ejemplo `01/07/2026 al 31/07/2026`.
- Aplicar automaticamente al elegir un atajo/mes o indicar claramente cambios pendientes antes de `Aplicar`; no mostrar selector nuevo con resultados viejos como si ya estuviera aplicado.

### 11.4 Contenido del periodo Mes

Resultados reales:

- Ingresos brutos del mes.
- Comision POS del mes.
- Ingreso neto del mes.
- Ventas completadas del mes.
- Servicios realizados del mes.
- Promedio por venta como dato auxiliar.

Proyeccion:

- Citas programadas.
- Servicios proyectados.
- Ingreso bruto proyectado.
- Saldo pendiente y adelantos solo como datos separados, sin sumarlos dos veces.

Desgloses:

- Rendimiento por empleado.
- Desglose diario real del mes.
- Opcionalmente anulaciones como datos secundarios.

### 11.5 Desglose diario

- Para mes, devolver todos los dias calendario, incluidos dias en cero.
- Para semana y custom, se recomienda el mismo contrato completo para evitar ambiguedad.
- Cada fila contiene fecha, ventas, servicios, bruto, POS y neto.
- El total en centavos de todas las filas debe ser exactamente igual al resumen del periodo bajo los mismos filtros.
- Las ventas distintas se cuentan por ID; joins de items o pagos no pueden duplicarlas.
- Orden cronologico ascendente para lectura mensual, salvo una decision visual expresa.
- Usar `items-per-page=-1` para un mes o mostrar paginacion real; nunca ocultar footer dejando filas inaccesibles.
- Proyeccion diaria, si se agrega despues, debe ser una coleccion separada; no mezclarla con ventas reales.

### 11.6 Zona horaria

- Interpretar fechas y meses en `America/Tegucigalpa`.
- Crear intervalos semiabiertos `[inicio local, inicio del dia posterior al final)`.
- Convertir ambos limites a UTC antes del query.
- Agrupar cada `sold_at` UTC de vuelta a Honduras.
- No usar timezone del navegador como autoridad.

## 12. Archivos que deberan modificarse

La siguiente lista es de implementacion futura; ninguno fue modificado en este analisis.

### Bloque A: movil

- `resources/js/Pages/Sales/Create.vue`.
- Nuevo `resources/js/Components/Sales/SaleMobileCheckout.vue`.
- Posible nuevo `resources/js/Components/Sales/SaleCheckoutReview.vue` si la extraccion evita duplicacion real.
- `resources/js/Components/Sales/SaleCart.vue`.
- `resources/js/Components/Sales/ConfirmSaleDialog.vue` si requiere safe area comun.
- `resources/js/types/sales.ts`.
- `resources/views/app.blade.php`.
- `resources/css/app.css` solo si el offset/safe area se centraliza globalmente.
- `tests/Feature/SaleCheckoutStructureTest.php`.
- Nuevas pruebas de componente o navegador responsive.

### Bloque B: notificaciones

- `app/Http/Controllers/NotificationController.php`.
- `app/Http/Resources/InternalNotificationResource.php`.
- `app/Providers/AppServiceProvider.php` si se reutiliza un builder de snapshot.
- `routes/web.php`.
- `resources/js/Components/Notifications/NotificationBell.vue`.
- `resources/js/Pages/Notifications/Index.vue`.
- `resources/js/Components/Notifications/NotificationListItem.vue` si cambia la semantica de apertura.
- `resources/js/types/notifications.ts`.
- Nuevo `resources/js/composables/useNotifications.ts`.
- `tests/Feature/InternalNotificationTest.php`.
- `tests/Feature/NotificationFrontendStructureTest.php`.
- Nuevas pruebas frontend/E2E del protocolo.

### Bloque C: anulacion

- Nueva migracion aditiva para estado y campos de anulacion en `sales`.
- `app/Models/Sale.php`.
- `app/Models/User.php` para relaciones de anulacion.
- `app/Models/AppointmentEvent.php`.
- Nuevo `app/Actions/Sales/CancelSaleAction.php`.
- Nuevo `app/Http/Requests/CancelSaleRequest.php`.
- `app/Http/Controllers/SalesController.php`.
- `app/Http/Resources/SaleReceiptResource.php`.
- `app/Http/Resources/AppointmentDetailsResource.php`.
- `app/Http/Resources/AppointmentHistoryResource.php`.
- `app/Actions/Appointments/BuildAppointmentHistoryAction.php`.
- `app/Support/Permissions.php`.
- `database/seeders/RoleSeeder.php`.
- `routes/web.php`.
- `resources/js/Pages/Sales/Receipt.vue`.
- Posible nuevo `resources/js/Components/Sales/CancelSaleDialog.vue`.
- `resources/js/types/sales.ts` y `resources/js/types/appointments.ts`.
- `app/Actions/Reports/BuildSalesSummaryAction.php` para secundarios de anulacion.
- `app/Actions/Notifications/PublishInternalNotificationAction.php` solo si el tipo requiere ajuste de contrato; la accion generica actual puede reutilizarse.
- Pruebas nuevas de migracion, anulacion, comprobante, cita, reporte y notificacion.
- Pruebas historicas que hoy afirman que `sales.cancel` o la ruta no existen.

### Bloque D: Ganancias

- `app/Http/Requests/SalesEarningsRequest.php`.
- `app/Support/ReportPeriod.php`.
- `app/Actions/Reports/BuildSalesSummaryAction.php`.
- `app/Actions/Reports/BuildAppointmentProjectionAction.php` para normalizar el nuevo filtro `month`.
- `app/Http/Controllers/EarningsController.php`.
- `resources/js/Pages/Earnings/Index.vue`.
- `resources/js/types/earnings.ts`.
- `tests/Feature/Phase3BEarningsTest.php`.
- `tests/Feature/Phase3B1CardPaymentTest.php`.
- `tests/Feature/Phase4EAppointmentProjectionTest.php`.
- `tests/Feature/EarningsStructureTest.php`.
- Nuevas pruebas unitarias de `ReportPeriod` y frontend mensual.

## 13. Migraciones necesarias

### En esta intervencion

Ninguna. No se creo ni ejecuto ninguna migracion.

### Para la implementacion aprobada

Una migracion aditiva de `sales`:

- `status`: ampliar enum de `completed` a `completed|canceled`.
- `canceled_at`: nullable.
- `canceled_by`: FK nullable y restrictiva.
- `cancellation_reason`: nullable para ventas existentes, obligatorio por regla al anular.

Consideraciones:

- Debe funcionar en MySQL productivo y en SQLite de pruebas.
- No modificar la migracion historica.
- No ejecutar `migrate:fresh`.
- Verificar datos antes y despues: numero de ventas, items y pagos sin cambios.
- El `down()` no debe intentar reducir el enum si existen ventas `canceled` sin una proteccion explicita.
- No crear migraciones para barra, notificaciones o periodos.
- No crear tablas precalculadas de reportes.
- Agregar indices solo con consulta y `EXPLAIN` que demuestren necesidad.

## 14. Pruebas necesarias

### 14.1 Barra movil

- Sin servicios no renderiza barra.
- Con uno y con varios servicios renderiza barra.
- `Ver resumen` existe a 320, 390, 420, 421, 839 y 840 px segun layout aplicable.
- `Cobrar` siempre existe cuando hay carrito.
- Venta normal y cita usan `SaleMobileCheckout`.
- Resumen abre como bottom sheet o fullscreen segun viewport.
- Cerrar conserva carrito y formulario.
- Safe area aparece en CSS y viewport declara `viewport-fit=cover`.
- La pagina agrega padding igual o mayor a la altura real de la barra.
- Cita con adelanto, saldo cero, excedente y servicio adicional conserva reglas.
- Portrait, landscape y teclado virtual.
- Chrome Android, Safari iPhone y WebView de WhatsApp.

### 14.2 Notificaciones

- Polling usa endpoint JSON con `fetch/axios`, no `router.reload`.
- Polling no envia `X-Inertia`.
- Polling no corre con pestaña oculta y consulta al volver visible.
- Solo hay una solicitud simultanea.
- Lectura individual JSON no produce error Inertia.
- Primera lectura cambia fila; segunda es idempotente.
- Contador se reemplaza por respuesta backend.
- Usuario no puede leer ni listar notificaciones ajenas.
- Lectura masiva solo cambia filas propias.
- Apertura espera lectura exitosa y luego usa `router.visit`.
- Notificacion ya leida navega sin PATCH.
- `Ver todas` usa navegacion Inertia, no recarga completa.
- Deduplicacion por ID al reconciliar polling.
- Respuestas 401/403/404/419 se manejan sin duplicar ni recargar.
- E2E confirma ausencia del modal de respuesta no Inertia.

### 14.3 Anulacion

- Owner puede anular.
- Administrator sin permiso recibe 403.
- Administrator con permiso explicito puede anular.
- Employee no recibe permiso por defecto y recibe 403.
- Usuario inactivo no puede anular.
- Motivo ausente, corto o mayor de 500 se rechaza.
- Solo `completed` puede anularse.
- Segunda anulacion secuencial se rechaza.
- Dos anulaciones concurrentes producen una sola transicion.
- Actor y hora son del servidor.
- Numero, fecha, vendedor, totales y cita no cambian.
- Lineas y pagos se conservan exactamente.
- No se puede reactivar.
- Fallo inducido en evento/notificacion revierte toda la anulacion.
- Comprobante en pantalla e impresion muestra `ANULADA`, actor, fecha y motivo.
- Reimpresion mantiene la marca.
- Venta desde cita mantiene cita `completed`.
- Adelanto aplicado y pago de adelanto no cambian.
- Historial de cita muestra evento y venta anulada.
- Notificacion interna se crea una vez por destinatario y revierte con la transaccion.

### 14.4 Ganancias

- Today usa limites Honduras.
- Week inicia lunes.
- Month acepta `YYYY-MM` y cubre todo el mes local.
- Compatibilidad temporal con `date` mensual, si se adopta, normaliza correctamente.
- Febrero bisiesto, meses de 30/31 dias y cambio diciembre-enero.
- Custom de un dia, 366 dias y rechazo de 367.
- Ventas anuladas se excluyen de bruto, POS, neto, ventas, servicios, promedio, empleado y diario.
- Secundarios de anulacion no se restan dos veces.
- Desglose mensual devuelve todos los dias definidos por el contrato.
- Dias sin actividad contienen cero.
- Suma en centavos del desglose diario iguala total mensual.
- Filtro de empleado usa ejecutor y no duplica ventas.
- Filtro de metodo combinado con periodo, empleado y modo funciona.
- Venta con adelanto/pago final no se duplica por joins.
- Proyeccion solo usa citas `scheduled` y limites Honduras.
- Modo actual, proyeccion y ambos no filtran datos no autorizados.
- Tabla muestra todas las filas o paginacion visible.

### 14.5 Regresion

- Suite completa anterior.
- `php artisan test` con el entorno realmente disponible; no declarar exito si falta driver.
- Suite con SQLite cargado solo para el proceso, como historial del proyecto.
- Pruebas dirigidas MySQL para migracion y concurrencia de anulacion.
- `npm run typecheck`.
- `npm run build`.
- Pint para PHP cambiado.
- `git diff --check` sin staging.

## 15. Riesgos

### Movil

- WebViews y Safari difieren al redimensionar el viewport con teclado.
- Safe area puede ser cero sin `viewport-fit=cover`.
- Un fixed bar con altura variable puede cubrir contenido si no se mide.
- Landscape puede cruzar breakpoints solo por ancho.
- No hay infraestructura E2E responsive actual; pruebas estructurales PHP no verifican geometria.

### Notificaciones

- La lectura ya puede haberse guardado aunque el usuario haya visto el error; no debe intentarse reparar decrementando localmente sin snapshot.
- Dos pestañas pueden leer o recibir hechos concurrentemente.
- Mezclar props Inertia y estado local sin una unica autoridad puede duplicar o revertir visualmente filas.
- URL almacenada debe validarse como interna antes de navegar.
- El orden conteo/recientes no es atomicamente perfecto; el contrato `as_of` ayuda a reconciliar, pero no sustituye transacciones si se exige snapshot estricto.

### Anulacion

- Anular administrativamente no ejecuta reembolso real.
- La propietaria debe conocer esa diferencia antes de usar tarjeta o efectivo.
- No existe Historial general de ventas; la accion estara inicialmente en el comprobante y en enlaces existentes.
- Un administrator autorizado necesita acceso minimo al comprobante ajeno que debe anular.
- La FK unica de cita impide venta de reemplazo.
- Reducir el enum en rollback es inseguro con datos anulados.
- `Sale` no es hoy plenamente inmutable y debe endurecerse sin romper la asignacion inicial de numero.
- Reportes secundarios deben elegir `sold_at` o `canceled_at` sin mezclar semanticas.

### Ganancias

- El daily actual carga filas en PHP; un rango de 366 dias puede requerir medicion de volumen antes de migrar a agregacion SQL.
- Agrupar por funciones SQL de fecha puede divergir entre MySQL y SQLite; conservar limites UTC y conversion Honduras.
- Joins simultaneos de items y pagos pueden multiplicar montos.
- Ocultar footer de Vuetify puede ocultar paginacion activa.
- `payment_method` es hoy filtro de pertenencia de la venta, no desglose monetario por metodo.
- El nombre comercial `Ganancias Generales` puede inducir a error; las etiquetas internas deben seguir diciendo ingresos, no ganancia neta.

## 16. Orden exacto de implementacion

Cada bloque debe terminar con pruebas dirigidas, suite de regresion aplicable, typecheck/build y revision manual antes de avanzar. No autoaprobar fases.

### Bloque A: barra movil y resumen compartido

1. Agregar pruebas estructurales que fallen por la exclusion de cita y ocultamiento a 420 px.
2. Extraer `SaleMobileCheckout.vue` como shell comun.
3. Derivar estado activo normal/cita en `Sales/Create.vue`.
4. Reemplazar barra y bottom sheet exclusivos por una unica instancia.
5. Agregar viewport seguro, insets y compensacion inferior medida.
6. Validar cierre sin perdida de estado y confirmacion correcta por flujo.
7. Ejecutar typecheck/build y matriz manual responsive.

### Bloque B: contrato de notificaciones

1. Agregar pruebas que reproduzcan solicitud Inertia contra JSON y definan los nuevos headers.
2. Hacer `GET /notifications` exclusivamente Inertia.
3. Convertir `recent` en snapshot JSON de conteo y recientes.
4. Estabilizar respuestas JSON de lectura individual/masiva y ownership.
5. Crear composable y reemplazar `router.patch`/polling por `fetch` sin `X-Inertia`.
6. Actualizar localmente contador/listas y navegar despues con `router.visit`.
7. Reemplazar `href` nativo de `Ver todas`.
8. Probar pestaña visible, doble click, ownership, errores y E2E sin full reload.

### Bloque C: anulacion transaccional

1. Crear pruebas de migracion y estado antes de aplicar schema.
2. Crear migracion aditiva y verificar conservacion de datos.
3. Agregar `STATUS_CANCELED`, casts, relaciones e inmutabilidad.
4. Crear permiso `sales.cancel`; asignar solo owner por defecto.
5. Crear Request y `CancelSaleAction` con transaccion/bloqueo.
6. Crear ruta y controlador con redirect 303 y flash.
7. Integrar evento de cita y notificacion en la misma transaccion.
8. Exponer estado/capacidad en Resource y dialogo del comprobante.
9. Marcar comprobante e Historial de cita como anulados.
10. Confirmar exclusion de todos los agregados reales.
11. Ejecutar concurrencia MySQL, suite, typecheck/build e impresion manual.

### Bloque D: periodos y mensual

1. Agregar pruebas unitarias de `ReportPeriod` y query `month`.
2. Normalizar contrato `date|month|date_from|date_to` en Request y acciones.
3. Agregar selector mensual y estado aplicado claro en Vue.
4. Completar filas diarias del periodo y garantizar reconciliacion en centavos.
5. Corregir paginacion/footer para que todos los dias sean accesibles.
6. Agregar secundarios de anulacion separados, filtrados por `sold_at`.
7. Verificar filtros combinados, modos y timezone.
8. Ejecutar suite, typecheck/build y reconciliacion manual mensual.

## 17. Prompt corto para ejecutar la implementacion completa

```text
Lee completamente docs/STUDIO_LEMUS_FINAL_STABILIZATION_PLAN.md y los tres planes fuente. Implementa exactamente los bloques A, B, C y D en ese orden: barra/resumen movil compartido con safe areas; contrato JSON separado de Inertia para notificaciones; anulacion transaccional auditada con sales.cancel, comprobante ANULADA, cita completed y reportes excluyentes; y Ganancias con Hoy/Semana/Mes/Personalizado, selector mensual y desglose diario Honduras. Crea solo la migracion aditiva de anulacion descrita, no borres ventas/items/pagos, no despliegues, no hagas git add/commit/push. Ejecuta pruebas dirigidas y completas, concurrencia MySQL de anulacion, Pint, typecheck y build; informa cualquier bloqueo y deja todo En pruebas, nunca Aprobado.
```

## Confirmacion de alcance

En esta intervencion no se modifico codigo funcional. No se modificaron Vue, rutas, controladores, modelos, permisos ni migraciones. No se ejecuto `git add`, commit, push ni despliegue. El unico archivo creado es `docs/STUDIO_LEMUS_FINAL_STABILIZATION_PLAN.md`.

Este plan queda pendiente de aprobacion expresa antes de implementar.

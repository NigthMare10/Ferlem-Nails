# Plan de implementacion de Caja, Ventas, Cobros, Comprobantes, Cierres y Reportes

Fecha de creacion: 2026-07-18  
Estado del documento: Fuente oficial de implementacion futura  
Alcance del producto: Un solo Studio Lemus, una ubicacion inicial, HNL, interfaz en espanol

Este documento define el orden operativo y los limites de las fases 2A a 2I, y registra los resultados de las fases implementadas. Toda decision del Registro de decisiones inicia como `Propuesta`; debe cambiar a `Aprobada` por confirmacion expresa del usuario antes de que una fase dependiente pueda implementarla.

El modulo de Agenda y Citas se gobierna mediante `docs/STUDIO_LEMUS_APPOINTMENTS_PLAN.md`. Ese plan separado no modifica los estados, dependencias o aprobaciones de las fases actuales de este documento.

## Integración actual de Agenda y Citas (2026-07-24)

- Gobierno y estados: `docs/STUDIO_LEMUS_APPOINTMENTS_PLAN.md` sigue siendo la fuente oficial. 4A está `Aprobada / Sí`; 4B, 4C, 4D, 4E y 4F están `En pruebas / No`. Esta integración no cambia estados, dependencias ni aprobaciones POS: 3A conserva `Aprobada / Sí`; 3B y 3B.1 conservan `En pruebas / No`; 3C-3E conservan `Pendiente / No`.
- Migraciones reales: existen las migraciones aditivas de `appointments`, `appointment_items`, segmentos por item, `appointment_events`, razón no-show, `appointment_deposits`, `appointment_deposit_refunds`, vínculo único `sales.appointment_id`, propósito de devolución, snapshots de checkout en `sale_items` y `sale_payments`. La orquestación final aplicó correctamente las seis migraciones nuevas de 4C/4D en MySQL como batch 8, sin `migrate:fresh`; 4F no creó migraciones.
- Modelos activos: `Appointment`, `AppointmentItem` y `AppointmentEvent` conservan snapshots, segmentos y auditoría append-only. `AppointmentDeposit` y `AppointmentDepositRefund` conservan monto original, fee POS, disponible, resolución y devoluciones inmutables. `Sale`, `SaleItem` y `SalePayment` se relacionan con la cita, el item reservado y la persona ejecutora; una cita tiene como máximo una venta y los registros financieros confirmados no se eliminan físicamente.
- Permisos: Agenda compone `appointments.access`, `view_own`, `view_all`, `create`, `perform`, `update`, `assign`, `cancel`, `mark_no_show`, `manage_deposit`, `resolve_deposit`, `convert_to_sale` y `view_projection`. Historial no inventa un permiso adicional: requiere usuario activo, `appointments.access` y `view_all|view_own`. Comprobantes mantienen `sales.reprint` más owner o `sales.view_own` sobre venta propia.
- Rutas: `GET /appointments` conserva calendario mensual y Agenda diaria solo `scheduled`; existen store, disponibilidad, detalle, update, reprogramación, adelanto, devolución de excedente, checkout, cancelación y no-show. `GET /appointments/history` lista los cuatro estados antes de la ruta dinámica y abre el mismo detalle autorizado en modo de solo lectura. Checkout usa `/sales/new?appointment={id}`, confirma por `POST /appointments/{appointment}/checkout` y termina en el comprobante existente. Proyección permanece separada en `GET /earnings`.
- Contratos y scopes: owner/`view_all` consulta todos los segmentos autorizados y filtra personal. Employee/`view_own` requiere `appointment_items.assigned_to = usuario`, recibe solo sus segmentos y no puede filtrar otra persona ni inferir servicios ajenos en una cita compartida. Historial filtra fecha local Honduras, estado, personal, clienta y nombre snapshot de servicio, pagina 20 registros y conserva filtros. Los detalles traducen auditoría segura sin mostrar JSON técnico.
- Fechas, dinero y estados: timestamps se almacenan UTC; días Honduras usan intervalos semiabiertos y rangos máximos de 366 días. Totales y fees se calculan en centavos. Solo `scheduled` bloquea disponibilidad; `completed`, `canceled` y `no_show` son terminales. Adelanto disponible = `amount - refunded - retained - applied`; la venta final conserva el valor completo y la proyección solo incluye citas programadas, separada de ventas, retenciones y devoluciones.
- UI actual: Agenda ofrece calendario mensual y vista diaria, Nueva cita, detalle, reprogramación, estados, adelanto y `Atender y cobrar`. Historial usa tabla desktop, cards móviles, filtros colapsables móviles y exactamente `Ver detalle`/`Ver comprobante` cuando está autorizado. Las completadas muestran hora y venta vinculada sin controles mutables. No existen vista semanal, recordatorios, WhatsApp, reserva pública, CRM, recurrencia, waitlist, inventario, factura fiscal, comisión laboral ni sucursales.
- Pruebas automáticas: 4F pasó 13 pruebas/230 aserciones y la suite permitida sin `NgrokPreviewTest` pasó 238/2,105. `php artisan test` directo no pasó: 13 pruebas/124 aserciones correctas y 228 errores `could not find driver`. Con SQLite cargado solo por proceso, la suite literal tuvo 238 correctas/2,113 aserciones y 3 fallos exclusivos de la prueba ngrok no rastreada por expectativas protegidas de Vite/host/bootstrap. Pint dirigido, `git diff --check` y build Vite pasaron; build transformó 1,205 módulos, con CSS 840.80 kB, JS 921.71 kB y la advertencia conocida de chunk mayor de 500 kB.
- Verificación MySQL: `optimize:clear`, `migrate`, `db:seed`, `route:list` y `migrate:status` terminaron correctamente. Las seis migraciones nuevas están en batch 8. Se conservaron 10 ventas, 15 líneas y 6 citas; las ventas históricas recibieron exactamente 10 pagos y no se crearon adelantos demo.
- Riesgos y validación manual pendiente: revisar planes SQL, volumen y concurrencia multiproceso MySQL; ejecutar E2E y comparación visual; validar filtros/scopes/compartidas/comprobantes/adelantos en 1440x900, 1024x768, 768x1024 y 390x844. SQLite sigue deshabilitado globalmente. Los archivos y pruebas ngrok permanecen aislados y sin cambios. Un proceso Vite preexistente en 5179 mantiene `public/hot`; no pertenece a esta implementación y `public/build` quedó compilado.

## Tablero de progreso

| Fase | Nombre | Estado | Dependencias | Aprobacion |
|---|---|---|---|---|
| 2A | Navegacion por rol y correcciones visuales | Aprobada | Fase 1B | Si |
| 2B | Apertura y caja actual | Sustituida | Cambio aprobado hacia POS simplificado | No aplica |
| 2C | Constructor de Nueva venta | Pendiente | 2B y decision D-003 | No |
| 2D | Cobro y comprobante | Pendiente | 2C y decisiones D-004, D-005, D-009, D-010, D-011 | No |
| 2E | Historial, detalle y reimpresion | Pendiente | 2D y decision D-007 | No |
| 2F | Anulacion | Pendiente | 2E y decision D-008 | No |
| 2G | Cierre de caja | Pendiente | 2F y decision D-006 | No |
| 2H | Reportes de ingresos | Pendiente | 2G y decision D-011 | No |
| 2I | Gastos, costos y ganancias | Pendiente | 2H y nuevas decisiones de costos/gastos | No |

Estados validos: `Pendiente`, `En desarrollo`, `En pruebas`, `Aprobada`, `Sustituida`. Solo puede existir una fase en desarrollo. Al terminar codigo y pruebas automaticas, la fase pasa a `En pruebas`; solo el usuario puede pasarla a `Aprobada` despues de ejecutar las pruebas manuales. `Sustituida` conserva historial de una fase retirada por una decision posterior.

## Revisión de alcance — POS simplificado

Cambio aprobado por el usuario el 2026-07-19. El roadmap 2B-2I se conserva como antecedente, pero deja de gobernar la operacion futura donde contradiga esta revision.

- No habra apertura manual de caja.
- No habra fondo inicial.
- No habra cierre manual diario.
- No habra efectivo esperado ni declarado.
- No se utilizara `cash_sessions` para autorizar ventas.
- El resumen diario se calculara automaticamente desde las ventas.
- La operacion principal sera Nueva venta.
- Employee inicia directamente en `/sales/new`.
- Owner conserva Inicio y tambien puede acceder a Nueva venta.
- Administrator accede segun permisos.
- La pantalla futura Ganancias Generales mostrara inicialmente ingresos, no ganancia neta.

`cash_sessions` queda como tabla legada sin uso operativo. No se modifica ni elimina su migracion, no se crean relaciones desde ventas y su limpieza se pospone a una fase tecnica posterior, despues de validar las ventas.

### Tablero POS simplificado

| Fase | Nombre | Estado | Dependencias | Aprobacion |
|---|---|---|---|---|
| 3A | Venta rapida y recibo | Aprobada | Revision POS simplificado y Servicios existentes | Si |
| 3B | Ganancias Generales e ingresos diarios | En pruebas | 3A aprobada | No |
| 3B.1 | Pago con tarjeta y comision POS | En pruebas | 3A aprobada y ventas/reportes existentes | No |
| 3C | Historial y detalle de ventas | Pendiente | 3A aprobada | No |
| 3D | Anulacion de ventas | Pendiente | 3C aprobada | No |
| 3E | Costos, gastos y ganancia real | Pendiente | 3D aprobada y decisiones financieras adicionales | No |

### Fase 3A - Venta rapida y recibo

**Estado:** Aprobada.

**Aprobacion:** Si.

**Objetivo:** seleccionar servicios activos, registrar una venta transaccional sin caja ni metodo de pago y generar un comprobante interno imprimible de 80 mm.

**Alcance:** `/sales/new`, carrito responsive, confirmacion, `sales`, `sale_items`, snapshots, numeracion `SL-000001`, recibo propio, idempotencia y permisos `sales.access`, `sales.create`, `sales.view_own`, `sales.reprint`.

**Fuera de alcance:** clientes, impuestos, descuentos, metodos/pagos, caja, historial completo, anulaciones, reportes, Ganancias Generales, costos y gastos.

**Condicion para avanzar:** 3A debe quedar `En pruebas` despues de migracion, pruebas y build; solo el usuario puede marcarla `Aprobada`.

**Resultado de implementacion 2026-07-19:**

- Migraciones reales `2026_07_19_130000_create_sales_table.php` y `2026_07_19_130100_create_sale_items_table.php`, aplicadas en batch 3 mediante `php artisan migrate`. Son reversibles, no modifican migraciones previas, no usan soft deletes y no incluyen `cash_session_id`, cliente, impuestos, descuentos ni metodo de pago.
- `sales` conserva vendedor/hora del servidor, subtotal/total exactos, cantidad total, estado `completed`, numero unico `SL-000001`, token unico de confirmacion y hash canonico del carrito. `sale_items` conserva snapshots de nombre, descripcion, duracion, precio, cantidad y total de linea; el servicio es nullable con `nullOnDelete`.
- Modelos `Sale` y `SaleItem` totalmente protegidos contra asignacion masiva, casts decimales/fecha, relaciones `soldBy`, `items`, `sale` y `service`; `User` agrega `sales` y `Service` agrega `saleItems`. El numero de venta no puede cambiar despues de asignarse.
- `CreateSaleRequest` acepta exclusivamente el token tecnico e items con IDs/cantidades, valida existencia, enteros y limite 1-50 con mensajes en espanol. Campos de precio, total, usuario, fecha o numero enviados por el navegador se ignoran.
- `CreateSaleAction` verifica usuario/permisos, consolida servicios repetidos, bloquea y vuelve a consultar el catalogo, rechaza inactivos, calcula en centavos enteros sin `float`, crea cabecera/items en una transaccion y devuelve la venta existente ante un reintento identico. El indice `sales_checkout_token_unique` impide duplicados; solo su conflicto se reconoce, otros errores SQL se propagan.
- Rutas: `GET /sales/new`, `POST /sales` y `GET /sales/{sale}/receipt` con permisos granulares. Employee/administrator solo consultan recibos propios; owner puede consultar cualquiera mediante verificacion backend explicita. No existe `GET /sales`, anulacion, edicion, eliminacion, reportes ni exportacion.
- Navegacion: owner conserva Inicio, Nueva venta y Configuracion autorizada; employee inicia en `/sales/new` y solo ve Nueva venta; administrator inicia en Nueva venta cuando tiene permisos y usa su primera seccion de Configuracion si se retiran. `/cash` solo redirige a `/sales/new` para usuarios autorizados y no consulta ni modifica Caja.
- Interfaz: `Sales/Create` incluye buscador, cards de servicios activos, carrito con multiples lineas, controles tactiles 1-50, total de servicios, total HNL, resumen sticky desktop, barra/bottom sheet movil y dialogo de confirmacion persistente durante proceso. No solicita cliente, impuestos, descuentos ni metodo de pago.
- Recibo: vista Inertia independiente, basada exclusivamente en snapshots de `SaleItem`, con marca, numero, fecha/hora Honduras, vendedor, lineas, cantidades, precios, total de servicios, total, agradecimiento, botones Imprimir/Nueva venta y CSS `@page`/`@media print` para 80 mm. No usa PDF ni contiene datos fiscales inventados.
- Permisos creados: `sales.access`, `sales.create`, `sales.view_own` y `sales.reprint`; owner, administrator y employee reciben los cuatro. Los permisos futuros `sales.view_all`, `sales.cancel`, `sales.apply_discount`, `reports.*` y `expenses.*` no se crearon. Seeders repetidos no duplican registros.
- Retiro de Caja: se eliminaron la ruta POST de apertura, acción, excepción, Form Request, Resource, página, diálogo y prueba de concurrencia operativa. Quedan como legado sin uso `2026_07_19_120000_create_cash_sessions_table.php`, la tabla con su fila preexistente, `CashSession.php`, relaciones históricas de `User` y `CashController@index` exclusivamente como redirección. Los tres permisos `cash.*` permanecen como filas históricas en MySQL, pero ningún rol los tiene asignados y no autorizan rutas o navegación.
- Pruebas: `php artisan test` directo fallo por `could not find driver`: 1 prueba paso y 58 terminaron con error SQLite. Cargando temporalmente `pdo_sqlite` y `sqlite3`, 59 pruebas y 279 aserciones pasaron. El INI temporal se elimino. La cobertura incluye permisos, landing, catalogo activo, validacion, consolidacion, calculo decimal, manipulacion, snapshots, rollback, idempotencia, recibos propios/ajenos, ausencia de Caja y seeders.
- Build: `npm run build` correcto, 1,187 modulos transformados. Permanece la advertencia no bloqueante por chunks mayores de 500 kB.
- Pruebas manuales: el usuario confirmo el flujo funcional completo para owner y employee y aprobo la fase. Las comprobaciones tecnicas ampliadas de impresion fisica, carrera multiproceso y dispositivos especificos permanecen como riesgos residuales, no como bloqueo de la aprobacion funcional otorgada.
- Riesgos: SQLite global sigue deshabilitado; la restriccion de idempotencia existe en MySQL pero la carrera multiproceso no se ejecuto en esta intervencion; la impresion depende del navegador/impresora; el nombre visible del vendedor proviene del usuario relacionado actual; el bundle supera 500 kB. No se crearon ventas de prueba en MySQL.

**Aprobacion manual 2026-07-19:** el usuario confirmo servicios activos, seleccion, cantidades, total, registro, comprobante y flujo completo para owner y employee. Fase 3A aprobada.

### Fase 3B - Ganancias Generales e ingresos diarios

**Estado:** En pruebas.

**Aprobacion:** No.

**Objetivo:** entregar a owner un reporte agregado de ventas completadas por periodo, empleado y dia operativo de Honduras, sin implementar historial o detalle individual.

**Terminologia financiera:** la pantalla conserva el nombre comercial `Ganancias Generales`, pero sus valores son exclusivamente ingresos brutos por servicios. No representan ganancia bruta, ganancia neta, utilidad ni margen porque todavia no existen costos ni gastos.

**Alcance aprobado:** ruta `GET /earnings`; permiso unico `reports.sales.view`; filtros Hoy, Esta semana, Este mes, Personalizado y empleado; total vendido, cantidad de ventas, cantidad de servicios, promedio por venta, rendimiento por empleado y resumen diario automatico.

**Cierre diario:** es un resumen calculado directamente desde ventas `completed`. No usa `cash_sessions`, no se persiste, no congela cifras y no requiere apertura, cierre, fondo o efectivo contado. Se muestran unicamente dias con ventas.

**Timezone:** periodos interpretados en `America/Tegucigalpa`, semana iniciando lunes y limites locales convertidos a UTC mediante intervalos semiabiertos sobre `sales.sold_at`.

**Fuera de alcance:** Historial y detalle de ventas (3C), anulacion (3D), costos, gastos y ganancia real (3E), metodos de pago, exportacion, PDF, graficas, comparaciones, comisiones y cierres manuales.

**Resultado de implementacion 2026-07-19:**

- Ruta real `GET /earnings`, nombre `earnings.index`, dentro de `auth` y `active` y protegida adicionalmente por `permission:reports.sales.view`. Responde con Inertia `Earnings/Index`; no existen endpoints publicos, descargas o exportaciones.
- Permiso unico `reports.sales.view`, centralizado en `Permissions.php`, creado idempotentemente y sincronizado de forma real solo al rol owner. Administrator y employee no lo reciben. Un administrator puede recibirlo posteriormente mediante RBAC sin cambios de codigo; la navegacion usa el permiso persistido y no el nombre del rol ni solamente `Gate::before`.
- `SalesEarningsRequest` valida `today`, `week`, `month`, `custom`, fecha de referencia, fechas personalizadas obligatorias, orden, maximo de 366 dias y `employee_id` existente. Los mensajes son en espanol y el backend vuelve a autorizar el permiso efectivo.
- `BuildSalesSummaryAction` calcula limites locales en `America/Tegucigalpa`, define la semana de lunes a domingo, convierte inicio y fin a UTC y consulta `sold_at` con intervalo semiabierto. No depende de la zona horaria del navegador o de funciones de fecha especificas de MySQL.
- Formulas: total vendido = `SUM(sales.total)`; ventas = `COUNT(sales)`; servicios = `SUM(sales.total_services)`; promedio = total en centavos / cantidad de ventas, redondeado a centavos y `0.00` sin ventas. Solo incluye `status = completed`; no suma `sale_items.line_total` ni usa `float` como calculo autoritativo.
- Consultas separadas: un agregado general SQL; un agregado SQL agrupado por `sales.sold_by` y usuario actual; una lectura minima de `sold_at`, `total` y `total_services` que agrupa en PHP por fecha Honduras para preservar reglas reales de timezone. Son tres consultas sobre ventas, sin N+1, sin cargar items y sin consultar `cash_sessions`.
- Indices utilizados: los existentes `sales_status_sold_at_index (status, sold_at)` y `sales_sold_by_sold_at_index (sold_by, sold_at)`. No se justifico ni creo una migracion o indice adicional.
- Interfaz: filtros por query string con Aplicar/Restablecer, loading y errores por campo; cuatro cards reales; tabla desktop y cards moviles de Rendimiento por empleado; Cierres diarios en tabla/lista; estado vacio y CTA autorizado. Muestra solo dias con ventas y no crea filas para dias vacios.
- Aclaraciones visibles: los valores corresponden a ingresos brutos por servicios y todavia no incluyen costos ni gastos. La pantalla no presenta total vendido como ganancia bruta, neta, utilidad o margen.
- Seguridad: invitado redirigido; inactivo bloqueado; employee y administrator sin permiso reciben 403 Inertia en espanol; owner y usuario con permiso acceden; IDs inexistentes se rechazan; la respuesta no incluye ventas, recibos, correos, roles o permisos de usuarios.
- Migracion: `php artisan migrate` respondio `Nothing to migrate`. No se crearon tablas, cierres o movimientos. `php artisan db:seed` creo/sincronizo correctamente la asignacion aprobada.
- Pruebas: `php artisan test` directo descubrio 76 pruebas; 1 paso y 75 terminaron con `could not find driver` porque SQLite sigue deshabilitado globalmente. Con `pdo_sqlite` y `sqlite3` cargados mediante INI temporal, pasaron 76 pruebas y 501 aserciones. Las 17 pruebas nuevas aportan 222 aserciones y cubren autorizacion, navegacion, periodos, 11:59 p. m./12:00 a. m., semana, mes, rangos, vendedores, dinero, estados, joins, dias, vacios, consultas y seeders. El archivo temporal se elimino al finalizar.
- Verificacion MySQL de solo lectura: el reporte reconcilio las tres ventas existentes, seis servicios, total `4920.00` y promedio `1640.00`, sin crear ni modificar ventas. El permiso existe una vez y esta asignado a owner, no a administrator o employee.
- Build: `npm run build` correcto con 1,190 modulos transformados; permanece la advertencia no bloqueante por chunks mayores de 500 kB.
- Pruebas manuales pendientes: reconciliar owner contra comprobantes; probar employee/owner/todos, today/week/month/custom y cierres diarios; confirmar 403 employee y continuidad de Nueva venta; revisar filtros, cards, cifras HNL y estado vacio en 1440x900, 1024x768, 768x1024 y 390x844.
- Riesgos: SQLite no carga por defecto; el resumen diario lee solo tres columnas de las ventas incluidas y el rango esta limitado a 366 dias, pero podria requerir una estrategia SQL especifica si el volumen futuro crece sustancialmente; el bundle supera 500 kB. No se crearon ventas demo ni datos financieros duplicados.

### Fase 3B.1 - Pago con tarjeta y comision POS

**Estado:** En pruebas.

**Aprobacion:** No.

**Objetivo:** integrar efectivo y tarjeta en la venta existente, conservar el total completo cobrado al cliente y registrar como snapshot la comision interna del POS y el ingreso neto recibido.

**Regla aprobada:** efectivo guarda comision `0.00` y neto igual al total. Tarjeta guarda tasa `4.00`, comision = total por 4% redondeada a centavos e ingreso neto = total menos comision. La comision nunca reduce precios, lineas, subtotal, total cobrado o total del comprobante.

**Datos:** agregar a `sales` los campos obligatorios `payment_method`, `card_fee_rate`, `card_fee_amount` y `net_amount`. Las ventas anteriores se normalizan como efectivo, con tasa/comision cero y neto igual al total, sin modificar sus importes existentes.

**Interfaz:** switch `Pago con tarjeta`, resumen interno de comision/neto, confirmacion protegida, metodo visible en el recibo sin exponer comision o neto, y Ganancias Generales con bruto, comision POS, neto y filtro por metodo.

**Fuera de alcance:** Historial y detalle (3C), anulaciones, gastos, costos, ganancia real, transferencias, pagos mixtos, referencias de tarjeta, datos sensibles, exportaciones y cierres manuales.

**Resultado de implementacion 2026-07-19:**

- Migracion reversible `2026_07_19_140000_add_payment_fields_to_sales_table.php`, aplicada en batch 4. Agrega `payment_method` enum `cash|card`, `card_fee_rate decimal(5,2)`, `card_fee_amount decimal(12,2)` y `net_amount decimal(12,2)`. Agrega nullable, normaliza y despues exige los cuatro campos sin defaults permanentes; no modifica migraciones anteriores.
- Normalizacion MySQL: las siete ventas existentes permanecen intactas en numero, vendedor, fecha, items, subtotal y total. Todas quedaron `cash`, tasa/comision `0.00` y neto igual al total. Reconciliacion posterior: bruto `9120.00`, comision `0.00`, neto `9120.00` y cero inconsistencias.
- `Sale` centraliza `PAYMENT_METHOD_CASH`, `PAYMENT_METHOD_CARD` y `CARD_FEE_RATE = '4.00'`, con casts decimales para tasa, comision y neto. El modelo continua totalmente protegido contra asignacion masiva.
- `CreateSaleRequest` exige exclusivamente `cash` o `card` con mensajes en espanol. Valores enviados para tasa, comision, neto, total, subtotal o precios no forman parte de los datos validados y no son autoridad.
- `CreateSaleAction` recibe el metodo validado, recalcula precios/total y, dentro de la misma transaccion, guarda el snapshot. Efectivo usa tasa/comision cero y neto = total. Tarjeta calcula en enteros `round(total_cents * 400 / 10000)`, conserva total completo y guarda neto = total menos comision. No usa `float`.
- Idempotencia: el hash historico del carrito se conserva. Un reintento con mismo token, carrito y metodo devuelve la venta; intentar cambiar efectivo por tarjeta con el mismo token se rechaza y no altera el snapshot guardado.
- Nueva venta: switch `Pago con tarjeta`, metodo visible y, solo para tarjeta, estimacion de Comision POS 4% e ingreso neto. El CTA sigue mostrando `Cobrar` con el total completo. El switch y los controles quedan bloqueados durante procesamiento; errores conservan carrito y seleccion.
- Confirmacion: muestra servicios, cantidades, total completo, metodo y, para tarjeta, comision/neto. Es persistente durante envio, evita doble confirmacion y conserva el texto `Confirmar y generar recibo`.
- Comprobante: muestra metodo Efectivo/Tarjeta y aviso discreto para tarjeta. Mantiene el total completo cobrado. El Resource no expone `card_fee_rate`, `card_fee_amount` o `net_amount` al cliente.
- Ganancias Generales: mantiene bruto, ventas, servicios y promedio; agrega Comision POS e Ingreso neto. Incluye la aclaracion de que el neto descuenta solo POS y no otros costos/gastos. Rendimiento por empleado y Cierres diarios muestran bruto, comision y neto en tabla desktop y cards moviles.
- Filtro adicional `payment_method` con Todos, Efectivo y Tarjeta via query string, combinable con periodo, fechas y empleado. Backend valida y aplica el filtro sobre la consulta autorizada de ventas `completed`.
- Formulas: bruto = `SUM(sales.total)`; comision = `SUM(sales.card_fee_amount)`; neto = `SUM(sales.net_amount)`; ventas = `COUNT(*)`; servicios = `SUM(total_services)`. Empleado y dia usan los mismos snapshots; cambiar la constante futura no recalcula ventas previas.
- Consultas: permanecen tres consultas de ventas sin `sale_items`, `cash_sessions` o N+1. No se agrego indice: el filtro existente `(status, sold_at)` restringe primero el periodo, `payment_method` tiene cardinalidad dos y el volumen actual no justifica otra estructura.
- Seguridad: employee sigue sin acceder a `/earnings`; la comision es siempre backend; campos manipulados se ignoran; cambiar metodo despues de guardar se rechaza; el recibo no expone costos internos; no se solicitan numeros, referencias u otros datos de tarjeta.
- Pruebas: `php artisan test` directo descubrio 88 pruebas; 1 paso y 87 terminaron con `could not find driver` porque SQLite continua deshabilitado globalmente. Con `pdo_sqlite`/`sqlite3` cargados mediante INI temporal pasaron 88 pruebas y 627 aserciones. El INI/directorio temporal se elimino.
- Build: `npm run build` correcto con 1,190 modulos transformados. Persiste la advertencia no bloqueante por chunks mayores de 500 kB. Pint final correcto.
- Pruebas manuales pendientes: ventas reales de L 1,000 en efectivo/tarjeta; validar total, recibo, bruto/comision/neto y manipulacion; revisar filtros/empleados/dias; confirmar 403 employee y responsive en 1440x900, 1024x768, 768x1024 y 390x844.
- Riesgos: las ventas anteriores se clasifican como efectivo segun regla aprobada porque el metodo real no era recuperable; `net_amount` no es ganancia neta contable; no existen referencias, conciliacion bancaria o pagos mixtos; SQLite no carga por defecto; el bundle supera 500 kB. No se crearon ventas demo o de tarjeta en MySQL.

## Diagnostico histórico previo al módulo de Agenda

### Arquitectura existente

- Laravel 13.20, Inertia Laravel 3.1, Vue 3.5, Vuetify 4.1, Vite 8.1, MySQL y sesiones Laravel.
- La aplicacion usa rutas web con sesion, CSRF, middleware `auth`, middleware personalizado `active` y middleware granular de Spatie Permission.
- Los controladores son directos y usan Eloquent, Form Requests, Resources y respuestas Inertia. No existe una capa de servicios, acciones de dominio, policies, auditoria ni precedentes de `DB::transaction` o `lockForUpdate`.
- `AppServiceProvider` comparte con Inertia usuario, roles, permisos y mensajes flash. Un `Gate::before` concede al owner todas las capacidades del backend.
- Los enlaces frontend usan rutas literales. La seguridad real debe continuar en middleware, controladores o Form Requests; ocultar una opcion no autoriza ni protege una ruta.
- En este diagnóstico previo existían `User`, `Service`, `Sale` y `SaleItem` como modelos activos. `CashSession` y su tabla permanecían como legado sin uso. Existía el reporte agregado Ganancias Generales y no existían pagos, clientes, anulaciones, movimientos, gastos, historial o detalle de ventas. La parte sobre pagos e Historial queda superada: hoy existen `SalePayment`, adelantos y el Historial de citas; aún no existen CRM de clientes, anulaciones de venta, gastos ni Historial general de ventas 3C.
- `services.price` ya usa `decimal(12,2)`. Los servicios pueden cambiar o eliminarse fisicamente, por lo que las ventas futuras deben conservar snapshots historicos.
- La zona horaria de Laravel es UTC, mientras la interfaz usa `es-HN` y HNL. Los dias operativos requieren una decision explicita.
- Las pruebas usan SQLite en memoria; la aplicacion local usa MySQL. La concurrencia real, bloqueos y restricciones especificas deben comprobarse tambien contra MySQL.
- No hay paquete de PDF ni impresion termica. No se necesita uno para el MVP si se usa una vista HTML y la impresion del navegador.

### Rutas y modulos existentes

- `/login`, `POST /login`, `POST /logout`.
- `/` muestra `Home` a todo usuario activo autenticado.
- `/configuration` redirige a la primera seccion autorizada.
- CRUD autorizado de `/configuration/users` y `/configuration/services`.
- Inicio muestra conteos reales filtrados por permisos. Configuracion contiene Usuarios y Servicios.
- Desde 3A existen `GET /sales/new`, `POST /sales` y `GET /sales/{sale}/receipt`. Desde 3B existe `GET /earnings`. `GET /cash` es solo una redireccion compatible; no existen apertura/cierre de Caja, historial, detalle, anulacion, exportacion o Gastos.

### Roles actuales

- `owner`: todos los permisos actuales, incluidos los cuatro de Ventas y `reports.sales.view`, y bypass global de Gate.
- `administrator`: permisos administrativos existentes y los cuatro permisos de Ventas de 3A, pero no recibe `reports.sales.view` y no puede crear ni modificar owners. El reporte puede asignarse posteriormente mediante RBAC.
- `employee`: `sales.access`, `sales.create`, `sales.view_own` y `sales.reprint`; no ve Inicio, Caja, Ganancias Generales ni Configuracion y entra a `/sales/new`.

### Riesgos previos que deben considerarse

- `UserController` intenta actualizar `is_active` por asignacion masiva, pero `User::$fillable` no incluye ese atributo. La persistencia de activacion/desactivacion debe corregirse y probarse como parte de 2A porque afecta el control de acceso.
- Los controles del ultimo owner hacen conteo y actualizacion sin bloqueo; es un riesgo de concurrencia preexistente, fuera del alcance financiero salvo aprobacion separada.
- `Gate::before` autoriza al owner en backend, pero la navegacion depende de permisos compartidos. Cada fase debe sincronizar tambien los permisos del rol owner para evitar acceso invisible.
- No debe ejecutarse `migrate:fresh` en bases con informacion.

## Principios de producto

1. La operacion principal debe requerir pocos pasos y lenguaje no tecnico.
2. Solo se muestran opciones completas, autorizadas y probadas.
3. El backend es autoridad para permisos, precios, descuentos, totales, caja activa y estados.
4. Dinero se almacena en `decimal(12,2)` o una precision mayor justificada; nunca `float`.
5. Ventas, pagos, anulaciones, cierres y comprobantes deben conservar historia inmutable.
6. Regla histórica del POS: el MVP no era un ERP, CRM, inventario, agenda, recursos humanos ni plataforma multiempresa. La exclusión de Agenda queda superada exclusivamente por el módulo 4A-4F; las demás exclusiones continúan vigentes.
7. Una fase debe poder probarse y aprobarse antes de iniciar la siguiente.

## Reglas globales obligatorias

1. Implementar una sola fase por intervencion.
2. No empezar una fase sin aprobar la anterior.
3. No crear opciones de navegacion sin funcionalidad.
4. No mostrar datos simulados.
5. No inventar requisitos fiscales.
6. No llamar ganancias a los ingresos.
7. No confiar en montos calculados solo en frontend.
8. No eliminar ventas confirmadas.
9. No cambiar precios historicos.
10. No modificar RBAC solo en frontend.
11. No ejecutar `migrate:fresh` sobre bases con informacion.
12. No guardar contrasenas fijas en el repositorio.
13. No agregar modulos fuera de alcance.
14. No actualizar dependencias sin necesidad.
15. No marcar pruebas como correctas si no pudieron ejecutarse.
16. No marcar una fase como Aprobada sin confirmacion del usuario.
17. Documentar decisiones que alteren dinero, permisos o auditoria.
18. Mantener toda la interfaz en espanol.
19. Mantener responsive desktop, tablet y movil.
20. Detenerse al finalizar cada fase.

## Navegacion recomendada por rol

### Resolucion inicial

`/` debe convertirse en un resolvedor de destino, no en una autorizacion implicita para ver Inicio.

- Owner: renderiza Inicio en `/`.
- Administrator: se dirige a `/sales/new` si tiene `sales.create` y existe caja abierta; si no, a `/cash` cuando tenga `cash.access`; despues a `/sales`, `/reports/sales` o `/configuration` segun el primer permiso disponible. Sin destino autorizado, muestra Acceso denegado.
- Employee: se dirige a `/sales/new` si tiene `sales.create` y existe caja abierta. Si no hay caja, se dirige a `/cash`. En Caja ve `Abrir caja` solo con `cash.open`; sin ese permiso ve una instruccion para solicitar apertura a un responsable.
- El destino `intended` solicitado antes del login solo se respeta si el usuario esta autorizado y el estado operativo lo permite; en caso contrario se aplica el resolvedor anterior.

### Owner

Orden futuro, mostrando solo modulos implementados: Inicio, Caja/Nueva venta, Ventas, Cierres, Reportes y Configuracion. Configuracion conserva Usuarios y Servicios.

### Administrator

No recibe automaticamente las capacidades de owner. Cada opcion se muestra por permiso efectivo. La recomendacion inicial explicita es permitir operar, abrir/cerrar caja, ver ventas, anular y consultar reportes de ventas/caja; no permitir gestionar owners, ver ganancias, exportar, eliminar gastos ni cambiar precios salvo concesion posterior.

### Employee

No muestra Inicio, Configuracion, Usuarios, Servicios administrativos, Reportes, Ganancias, costos internos ni cierres ajenos. Muestra Caja, Nueva venta y sus comprobantes recientes de acuerdo con permisos. `cash.open` y `cash.close` no se asignan por defecto; pueden concederse directamente a un empleado responsable.

### Escritura manual de URL por employee

| URL | Comportamiento recomendado |
|---|---|
| `/` | Redirigir a `/sales/new` con caja abierta y permiso de venta; en otro caso a `/cash`. |
| `/configuration` | Responder 403 con pantalla Inertia Acceso denegado; no redirigir ocultando el intento. |
| `/configuration/users` | Responder 403 por `settings.access`/`users.view`. |
| `/configuration/services` | Responder 403 por `settings.access`/`services.view`. El selector de venta no concede acceso administrativo. |
| `/reports` o `/reports/sales` | Responder 403 sin permiso de reportes. |
| Venta de otro usuario | Responder 403 salvo `sales.view_all`. |
| Cierre ajeno | Responder 403 salvo `cash.view_all`. |

## Primera correccion visual: Configuracion

Componente identificado: `resources/js/Layouts/ConfigurationLayout.vue`.

La causa es la distribucion `md="3" lg="2"` de la columna secundaria y `md="9" lg="10"` del contenido. En pantallas grandes la navegacion pierde ancho y Vuetify trunca los titulos.

Correccion requerida en Fase 2A:

- Mantener un ancho util aproximado de 220 a 260 px o tres columnas de la grilla para la navegacion secundaria en escritorio.
- Mostrar completos `Usuarios` y `Servicios`, con icono y texto, sin ellipsis innecesario.
- Conservar estado activo claramente visible y sidebar sticky.
- Compensar la columna principal sin comprimir tablas o formularios; respetar el maximo de contenido existente.
- En tablet, usar tabs cuando la combinacion del drawer principal y la navegacion secundaria no deje al menos 220 px. No deben coexistir dos sidebars permanentes.
- En movil, usar tabs desplazables o menu compacto; para las dos secciones actuales se recomiendan tabs.
- Verificar ausencia de scroll horizontal en 1440x900, 1024x768, 768x1024 y 390x844.

## Decisiones funcionales recomendadas

### Caja

Alternativas evaluadas:

- Global: corresponde a la unica caja fisica inicial y simplifica saldos e historial.
- Por usuario: fragmenta una caja compartida y exige transferencias entre empleados.
- Por dispositivo: depende de identificar equipos de forma confiable y complica reemplazos/navegadores.
- Por turno: modela bien la operacion, pero no debe permitir varios turnos abiertos simultaneamente en el MVP.

Recomendacion: una sesion global por turno operativo, con maximo una sesion `open` en toda la instalacion. No pertenece al usuario ni al dispositivo; registra quien abre y quien cierra. Cualquier usuario con `cash.access` y `sales.create` puede operar sobre ella.

- Abre owner, administrator autorizado o employee con `cash.open` explicito.
- Cierra owner, administrator autorizado o employee con `cash.close` explicito.
- El cambio de empleado no cierra la caja; cada venta conserva `sold_by`.
- Cerrar navegador o expirar sesion no cierra caja; el estado es persistente.
- Una caja del dia anterior queda marcada como pendiente/atrasada. No se abre otra; un autorizado debe revisarla y cerrarla. Nunca se cierra automaticamente.
- Dos aperturas se protegen con transaccion y una restriccion unica de guardia activa. Solo una confirma; la otra recibe un mensaje de caja ya abierta.
- Dos cierres bloquean la misma fila con `lockForUpdate`; solo el primero confirma y el segundo recibe caja ya cerrada.
- Una caja cerrada es inmutable y no puede reabrirse ni editarse.
- Las correcciones se registran como eventos/notas append-only con usuario, momento, motivo, valor anterior y valor indicado. Hasta que exista un movimiento financiero aprobado, no alteran silenciosamente el cierre.

### Nueva venta

Recomendacion para todos los dispositivos: pagina dedicada para construir la venta, modal solo para confirmar cobro y pagina independiente para comprobante.

- Desktop: dos areas, selector a la izquierda y resumen sticky a la derecha.
- Tablet: dos columnas si cada una conserva ancho util; en orientacion estrecha, una columna con acceso persistente al resumen.
- Movil: cards/lista, resumen inferior expandible y boton tactil fijo `Cobrar L 780.00`; sin tablas densas ni scroll horizontal.
- No se recomienda toda la venta en modal: pierde contexto, espacio y recuperacion ante errores.
- No se recomienda una unica pagina que mezcle construccion, cobro e impresion: aumenta riesgo de doble envio y dificulta aislar el comprobante.

### Metodos de pago

MVP: efectivo, tarjeta y transferencia. Pago mixto queda fuera de 2D y requiere una fase futura aprobada. El modelo admite varios registros desde el inicio, pero el MVP valida exactamente un pago por venta.

- Efectivo: `amount` igual al total, `amount_received` obligatorio y mayor o igual al total, `change_amount` calculado por backend, `reference` nulo.
- Tarjeta: `amount` igual al total; no se guarda numero de tarjeta. `reference` es opcional, maximo 100 caracteres, para codigo de autorizacion o ultimos cuatro digitos no sensibles.
- Transferencia: `amount` igual al total y `reference` obligatoria, maximo 100 caracteres.
- Toda referencia se normaliza, valida y escapa. No se integran pasarelas de pago en el MVP.

### Comprobante

El MVP genera un `Comprobante de venta` o `Recibo interno`, nunca una factura fiscal. No incluye SAR, CAI, RTN, rangos, impuestos ni numeracion fiscal sin requisitos legales posteriores.

Recomendacion: ruta HTML independiente, preparada para papel termico de 80 mm, abierta en una nueva pestana y con `window.print()` iniciado por accion del usuario. No usar PDF ni paquete adicional. El fallo de impresion no revierte la venta; el comprobante queda disponible para reimpresion.

Contenido minimo: marca, numero, fecha/hora, usuario que cobro, servicios, cantidades, precios unitarios, totales de linea, subtotal, descuento, total, metodo, monto recibido, cambio y agradecimiento. Direccion, telefono, identidad tributaria y pie configurable se agregan cuando el negocio entregue datos validos.

### Ingresos y ganancias

- Ingresos: total de ventas completadas y cobradas, excluyendo anuladas.
- Ganancia bruta: ingresos menos costos historicos estimados de los servicios vendidos.
- Ganancia neta: ingresos menos costos, gastos, devoluciones y otras salidas.
- Con el modelo actual solo pueden mostrarse conteos y, despues de 2D, ingresos cobrados. Ningun total debe llamarse ganancia.
- En 2I se agrega costo estimado al servicio y snapshot de costo a la venta. El costo no debe reconstruirse usando el valor actual del servicio.
- Gastos y movimientos entran en 2I. Solo entonces se habilita ganancia bruta; ganancia neta se habilita cuando gastos, salidas y devoluciones cubran todos los conceptos incluidos y la formula quede visible.
- Cada indicador financiero debe mostrar periodo, moneda, estados incluidos y formula o ayuda contextual.

### Anulacion de ventas

- Estados del MVP: `completed` y `canceled`. No hay eliminacion fisica ni soft delete.
- Anular requiere `sales.cancel`, motivo obligatorio de 10 a 500 caracteres, `canceled_by` y `canceled_at` del servidor.
- Owner puede anular. Administrator solo con permiso explicito, recomendado por defecto. Employee no lo recibe por defecto.
- La venta anulada conserva items y pagos. Se excluye de ingresos netos y de efectivo esperado, pero aparece separada en auditoria y reportes de anulaciones.
- Si el pago fue efectivo y la caja sigue abierta, la anulacion reduce el efectivo esperado porque representa devolucion. Para tarjeta/transferencia, el sistema no ejecuta reembolso externo; exige confirmar que la reversa externa fue atendida y conservar referencia en el motivo.
- En el MVP no se anula una venta de una caja cerrada. Una correccion posterior necesita un flujo futuro de devolucion/ajuste auditado.
- Una venta anulada no puede reactivarse. Se crea una venta nueva si corresponde.

### Descuentos y precios

- Solo pueden venderse servicios activos.
- Backend vuelve a consultar servicio, estado y precio dentro de la transaccion; ignora precios calculados por el navegador.
- `sale_items` copia nombre, descripcion y precio unitario. Cambios o eliminaciones posteriores no alteran la venta.
- MVP permite descuento fijo en HNL a nivel de venta solo con `sales.apply_discount`, con motivo obligatorio. Debe ser mayor que cero y menor que el subtotal. No hay descuento porcentual ni por linea.
- Employee no recibe descuento por defecto.
- No se permite cambiar precio unitario, conceptos manuales, servicios no configurados ni cortesias en el MVP. `sales.override_price` queda reservado y no se crea hasta aprobar esa funcionalidad.
- Cantidad es entero positivo con limite operativo validado. El backend recalcula subtotal, descuento y total.

### Cierre de caja

Recomendacion: cierre ciego. Primero se solicita efectivo contado; despues de confirmar se muestran esperado y diferencia. Reduce el ajuste intencional del monto declarado y mantiene un flujo sencillo.

- `opening_amount`: fondo inicial declarado al abrir.
- `cash_sales`: pagos en efectivo de ventas completadas de la sesion.
- `card_sales`: pagos con tarjeta, informativos; no aumentan efectivo esperado.
- `transfer_sales`: transferencias, informativas; no aumentan efectivo esperado.
- `entries`, `outputs`, `expenses`: no existen antes de 2I y no deben mostrarse como acciones ficticias. Desde 2I se incluyen movimientos confirmados.
- `expected_cash = opening_amount + cash_sales + cash_entries - cash_outputs - cash_expenses`.
- `declared_cash`: efectivo contado, obligatorio y no negativo.
- `difference = declared_cash - expected_cash`.
- Nota de cierre opcional cuando diferencia es cero y obligatoria cuando no es cero.
- Se registra usuario y hora del servidor. El cierre usa transaccion, bloqueo y snapshots de totales.

## Flujo de Nueva venta

1. Verificar usuario activo, `sales.create` y caja abierta.
2. Mostrar solo servicios activos desde backend.
3. Buscar por nombre o descripcion sin perder la seleccion.
4. Mostrar nombre, duracion y precio HNL.
5. Anadir uno o varios servicios.
6. Modificar cantidad con controles tactiles y limites.
7. Retirar servicios.
8. Mostrar subtotal preliminar.
9. Mostrar descuento solo con `sales.apply_discount`; exigir motivo.
10. Mostrar total preliminar.
11. Abrir `PaymentDialog` sin perder carrito.
12. Seleccionar efectivo, tarjeta o transferencia.
13. En efectivo, ingresar monto recibido.
14. Mostrar cambio preliminar.
15. Validar pago suficiente y campos del metodo.
16. Confirmar una vez; bloquear controles y cierre externo del modal.
17. Enviar un `checkout_token` UUID unico para idempotencia.
18. En backend, iniciar transaccion, bloquear caja actual, validar estado, recargar servicios activos, recalcular precios/descuento/totales y crear venta, items y pago.
19. Generar numero unico e inmutable y confirmar transaccion.
20. Mostrar venta completada con total, cambio, numero y acciones Imprimir/Otra venta.
21. Abrir comprobante independiente al solicitar impresion.
22. Limpiar carrito solo despues de respuesta confirmada; Otra venta vuelve a `/sales/new`.

### Casos limite

- Precio cambiado: comparar `updated_at` o firma de version. Responder conflicto de carrito, actualizar precio visible y exigir reconfirmacion; no cobrar silenciosamente otro total.
- Servicio desactivado: rechazar el cobro, identificar la linea y retirarla/actualizarla solo con confirmacion del usuario.
- Error de conexion: mantener carrito y token; al reintentar, el token devuelve la venta ya creada o procesa una sola vez.
- Refresco: conservar borrador no confiable en `sessionStorage`, asociado a usuario y caja. Al restaurar, revalidar todo contra backend.
- Doble clic: boton deshabilitado durante procesamiento y restriccion unica sobre `checkout_token`.
- Cobros simultaneos: transacciones independientes asociadas a la misma caja; bloqueo breve para validar caja, numeros basados en identificador unico y restricciones de base.
- Numero duplicado: indice unico; la estrategia `SL-` mas ID global evita secuencias diarias concurrentes. Un conflicto debe abortar completamente.
- Fallo de impresion: venta sigue completada y puede reimprimirse.
- Sesion expirada/419: conservar borrador local, solicitar login, volver al destino permitido y consultar el token antes de reintentar.

## Diseno de Nueva venta y cobro

### Desktop

- Area izquierda flexible: buscador, filtros minimos y grilla/lista de `ServiceCard` con nombre, duracion, precio y Anadir.
- Area derecha de 360 a 420 px: carrito sticky, cantidades, subtotales, descuento autorizado, total y Cobrar.
- El selector conserva foco y permite agregar rapidamente sin modales repetitivos.

### Tablet

- Dos columnas a partir del ancho donde selector y resumen conserven legibilidad.
- Una columna en orientacion vertical; resumen accesible mediante panel sticky/colapsable sin perder el carrito.

### Movil

- Una lista de cards compactas y tactiles.
- Resumen en bottom sheet o seccion colapsable; se recomienda bottom sheet no modal bloqueante.
- Boton fijo `Cobrar L X.XX`, respetando safe area.
- No usar tablas, celdas truncadas ni scroll horizontal.

### PaymentDialog

- Muestra total a pagar y resumen corto.
- Selector de metodo con etiquetas Efectivo, Tarjeta y Transferencia.
- Efectivo: monto recibido, accesos de monto exacto y cambio preliminar.
- Tarjeta: referencia opcional, sin datos sensibles.
- Transferencia: referencia obligatoria.
- Confirmar y Cancelar; mientras procesa, Confirmar muestra carga, no acepta doble clic y el dialogo no se cierra por click externo, Escape o navegacion accidental sin advertencia.
- Los errores de validacion permanecen junto al campo. Los conflictos de caja/servicio/precio regresan al constructor con explicacion.
- Los calculos frontend son informativos. La respuesta del backend reemplaza subtotal, descuento, total y cambio definitivos.

## Comprobante de venta

- Ruta independiente sin drawer ni app bar durante impresion.
- Hoja `@page` de 80 mm, margenes termicos reducidos, tipografia legible en negro y sin fondos innecesarios.
- Vista normal con acciones Imprimir y Volver; `@media print` oculta acciones.
- Datos: Studio Lemus, `sale_number`, fecha/hora en `America/Tegucigalpa`, cobrador, lineas, cantidades, precios, subtotal, descuento/motivo cuando aplique, total, metodo, recibido/cambio para efectivo, referencia parcialmente visible cuando aplique y agradecimiento.
- Etiqueta permanente: `Comprobante de venta` o `Recibo interno`.
- Datos empresariales posteriores: nombre comercial legal validado, direccion, telefono, identidad tributaria si corresponde y pie configurable. No se muestran placeholders legales.
- Reimpresion usa los mismos snapshots. No regenera precios ni numero.

## Matriz de pantallas

Las pantallas combinadas reducen navegacion: `Caja` integra caja cerrada, apertura autorizada, caja actual y accion de cierre; `Detalle de venta` integra resumen, resultado exitoso y reimpresion; `Reportes de ingresos` integra diario, semanal y mensual mediante filtros; el modal de cobro no es una ruta.

| Pantalla | Ruta propuesta | Acceso / permiso | Objetivo e informacion | Acciones | Vacio, carga y errores | Desktop / movil | Fase / dependencias |
|---|---|---|---|---|---|---|---|
| Login | `/login` | Invitado | Autenticar y resolver destino por rol | Ingresar, mostrar clave | Carga de envio; credenciales/inactivo | Composicion dividida / formulario compacto | Existente; ajustar redireccion en 2A |
| Acceso denegado | Respuesta 403 | Autenticado sin permiso | Explicar que la accion no esta autorizada | Volver al destino permitido | Sin datos; 403 consistente | Card centrada / ancho completo | 2A; manejo Inertia de errores |
| Redireccion por rol | `/` | `auth`, `active` | Resolver owner dashboard o destino operativo | Redireccion segura | Sin destino: Acceso denegado | Sin UI intermedia | 2A; D-002 |
| Inicio owner | `/` | Owner | Estado de caja, ventas e ingresos disponibles segun fase | Ir a Caja, Nueva venta, reportes | Metricas no disponibles, skeleton, error de consulta | Grid de metricas / cards | 2A y enriquecimiento 2B-2H |
| Caja | `/cash` | `cash.access` | Combinar caja cerrada, abrir caja, estado actual y cierre autorizado | Abrir, ir a venta, cerrar | Sin caja; carga; ya abierta/cerrada/conflicto | Resumen amplio / cards y CTA fijo | Shell 2A; datos 2B; cierre 2G |
| Abrir caja | Dialogo en `/cash` | `cash.open` | Capturar monto inicial y nota | Confirmar, cancelar | Procesando; monto invalido; apertura concurrente | Dialogo / fullscreen en movil | 2B; cash_sessions |
| Nueva venta | `/sales/new` | `sales.access`, `sales.create`, caja abierta | Seleccionar servicios y construir carrito | Buscar, anadir, cantidad, retirar, cobrar | Sin servicios; skeleton; caja cerrada; conflicto de catalogo | Dos areas / cards y resumen inferior | 2C; caja actual y servicios activos |
| Resumen/modal de cobro | Dialogo en `/sales/new` | `sales.create` y descuentos segun permiso | Confirmar metodo, recibido, referencia y cambio | Confirmar, cancelar | Procesando; insuficiente; 419; conflicto; duplicado | Dialogo medio / fullscreen | 2D; sales, items, payments |
| Venta completada | Estado de `/sales/{sale}` | Propia o `sales.view_all` | Mostrar numero, total, cambio y resultado | Imprimir, otra venta, ver detalle | Carga; 403/404 | Card de resultado / CTA apilados | 2D; venta confirmada |
| Historial de ventas | `/sales` | `sales.view_own` o `sales.view_all` | Ventas recientes, filtros, estado, total y usuario permitido | Filtrar, abrir detalle | Sin ventas; skeleton; filtros invalidos | Tabla / cards | 2E; D-007 |
| Detalle de venta | `/sales/{sale}` | `sales.view_detail` mas alcance | Snapshots, pagos, estado, auditoria | Reimprimir; anular con permiso | 403, 404, carga | Secciones/cards / una columna | 2E; anulacion en 2F |
| Comprobante / reimpresion | `/sales/{sale}/receipt` | `sales.reprint` mas alcance | Recibo interno 80 mm | Imprimir, volver | 403/404; error de carga; impresion externa | Preview centrado / ancho termico | 2D inicial; listado 2E |
| Comprobantes recientes | Seccion de `/sales` o `/cash` | `sales.view_own` | Ultimas ventas propias de caja actual | Abrir, reimprimir | Sin ventas; carga | Lista lateral / cards | 2E; D-007 |
| Cierres | `/cash-closures` | `cash.view_history` o `cash.view_all` | Listar sesiones, responsables, montos y diferencia | Filtrar, abrir detalle | Sin cierres; skeleton; rango invalido | Tabla / cards | 2G; cajas cerradas |
| Detalle de cierre | `/cash-closures/{cashSession}` | Permiso y alcance | Apertura, metodos, esperado, declarado, diferencia y notas | Imprimir/resumir si se aprueba; agregar nota auditada | 403/404; cierre incompleto | Resumen en columnas / cards | 2G; D-006 |
| Resumen diario | `/reports/sales?period=daily` | `reports.sales.view` | Ingresos, ventas y metodos del dia local | Cambiar fecha, abrir ventas | Sin ventas; carga; fecha invalida | Metricas y tabla / cards | 2H; D-011 |
| Resumen semanal | `/reports/sales?period=weekly` | `reports.sales.view` | Ingresos por dia y total semanal | Cambiar semana | Sin ventas; carga | Grafico simple/tabla / lista | 2H; D-011 |
| Reportes de ingresos | `/reports/sales` | `reports.sales.view` | Unificar diario, semanal, mensual, metodos y anulaciones | Filtrar periodo; exportar solo con permiso futuro | Sin datos; skeleton; error de rango | Filtros y resumen / filtros colapsables | 2H; ventas/cierres |
| Configuracion | `/configuration` | `settings.access` | Entrada a secciones autorizadas | Ir a Usuarios/Servicios | Sin secciones: 403 | Sidebar corregido / tabs | Existente; correccion 2A |
| Usuarios | `/configuration/users` | `users.view` y settings | Administrar usuarios | CRUD autorizado | Existentes | Tabla / cards | Existente |
| Servicios | `/configuration/services` | `services.view` y settings | Catalogo y, en 2I, costo restringido | CRUD autorizado | Existentes | Tabla / cards | Existente; costo 2I |

## Modelo de datos conceptual

No se crean migraciones desde este documento. Los nombres y restricciones siguientes son el contrato conceptual que cada fase debe revisar antes de implementar.

### Convenciones comunes

- IDs bigint sin signo, claves foraneas indexadas y timestamps del servidor almacenados en UTC.
- Visualizacion y limites diarios en `America/Tegucigalpa`.
- Montos `decimal(12,2)` como minimo, no negativos salvo una diferencia que puede ser negativa. Nunca `float`.
- Usuarios referenciados con `restrictOnDelete`; la aplicacion actual no elimina usuarios.
- No usar soft deletes para registros financieros. Estados y auditoria conservan la historia.
- Restricciones de base de datos complementan validacion, permisos y transacciones.
- Registros completados/cerrados no se editan mediante CRUD general.

### `cash_sessions`

Responsabilidad: representar un turno global desde apertura hasta cierre.

Campos base: `id`, `opened_by`, `closed_by` nullable, `opened_at`, `closed_at` nullable, `opening_amount`, `expected_cash` nullable, `declared_cash` nullable, `difference` nullable, `status`, `opening_notes`, `closing_notes`, timestamps. Agregar conceptualmente `active_guard` nullable con valor constante para la sesion abierta.

- Relaciones: pertenece a usuario que abre y opcionalmente a quien cierra; tiene muchas ventas y, desde 2I, movimientos/gastos.
- Integridad: `opened_at` y `opening_amount` obligatorios; campos de cierre nulos mientras `open`; completos al estar `closed`; `closed_at >= opened_at`.
- Indices: `status`, `opened_at`, `closed_at`, `opened_by`, `closed_by`; unico nullable en `active_guard` para una sola caja abierta en MySQL.
- Decimales: importes de caja en `decimal(12,2)`; diferencia admite signo.
- Auditoria: conservar responsables, tiempos, notas y snapshot del calculo de cierre.
- Concurrencia: apertura dentro de transaccion y guardia unica; cierre con `lockForUpdate` y comprobacion de estado.
- Inmutabilidad: despues de cerrar no se reabre, borra ni actualiza. Correcciones son append-only y visibles.
- Soft deletes: no.

### `sales`

Responsabilidad: cabecera inmutable de una venta cobrada.

Campos base: `id`, `sale_number`, `cash_session_id`, `sold_by`, `sold_at`, `subtotal`, `discount`, `discount_reason` nullable, `total`, `status`, `checkout_token`, `canceled_at`, `canceled_by`, `cancellation_reason`, timestamps.

- Relaciones: pertenece a caja y vendedor; tiene muchos items y pagos; opcionalmente pertenece a usuario anulador.
- Integridad: caja obligatoria en MVP; subtotal/total no negativos; descuento menor al subtotal; `completed` o `canceled`; campos de anulacion completos solo al anular.
- Indices: unicos `sale_number` y `checkout_token`; compuestos `(cash_session_id, sold_at)`, `(sold_by, sold_at)`, `(status, sold_at)`.
- Decimales: `decimal(12,2)` para subtotal, descuento y total.
- Auditoria: vendedor, hora, anulador, hora y motivo; no reutilizar numero.
- Concurrencia: token idempotente, numero basado en ID y transaccion atomica.
- Inmutabilidad: items, pagos, total y numero no cambian despues de completar. Solo transicion unica de `completed` a `canceled`.
- Soft deletes: no.

### `sale_items`

Responsabilidad: conservar cada servicio vendido y su snapshot historico.

Campos base: `id`, `sale_id`, `service_id` nullable, `service_name`, `service_description` nullable, `unit_price`, `quantity`, `line_total`, y desde 2I `unit_cost` nullable y `line_cost` nullable, timestamps.

- Relaciones: pertenece a venta; servicio original es opcional con `nullOnDelete` para no impedir conservar historia.
- Integridad: nombre obligatorio, cantidad entera positiva, `line_total = unit_price * quantity` verificado en backend.
- Indices y unicos: `sale_id`, `service_id`, opcional `(service_id, created_at)` para reportes; unico `(sale_id, service_id)` en MVP para consolidar el mismo servicio mediante cantidad.
- Decimales: precios/costos/totales `decimal(12,2)`.
- Auditoria: la venta, servicio original nullable y snapshots permiten reconstruir que se cobro.
- Concurrencia: todos los items se insertan en la transaccion de su venta; no se actualizan despues del commit.
- Inmutabilidad: snapshots no se actualizan si cambia o se elimina el servicio.
- Soft deletes: no.

### `payments`

Responsabilidad: registrar como se cubrio una venta.

Campos base: `id`, `sale_id`, `method`, `amount`, `amount_received` nullable, `change_amount` nullable, `reference` nullable, timestamps.

- Relaciones: pertenece a venta. El esquema admite varios pagos, aunque 2D exige uno exactamente.
- Integridad: metodo permitido; monto positivo e igual al total en MVP; efectivo requiere recibido suficiente/cambio; transferencia requiere referencia; tarjeta no almacena datos sensibles.
- Indices y unicos: `sale_id`, `method`, `(method, created_at)`. Deliberadamente no hay unico por `sale_id` para permitir pago mixto futuro; la regla de un pago MVP vive en validacion/transaccion.
- Decimales: todos los montos `decimal(12,2)`.
- Auditoria/inmutabilidad: pagos no se editan ni eliminan al anular; reportes consideran el estado de la venta.
- Concurrencia: se crean dentro de la misma transaccion de venta.
- Soft deletes: no.

### `cash_movements`

Decision: no entra en el MVP operativo de 2B a 2H. Entra en 2I junto con gastos, porque agregar entradas/salidas sin formulas, permisos y auditoria completos produciria cierres engañosos.

Responsabilidad futura: entradas y salidas manuales de efectivo no originadas por ventas, con `cash_session_id`, `type`, `amount`, `reason`, `recorded_by`, `occurred_at`, `reverses_movement_id` nullable y timestamps.

- Relaciones: pertenece a caja y usuario responsable; puede referenciar un movimiento original cuando es una reversa; un gasto efectivo puede vincularse uno a uno con su movimiento.
- Integridad: caja abierta al registrar, monto positivo, tipo `in`/`out`, razon obligatoria y una reversa con signo/tipo opuesto. No aceptar movimiento sobre caja cerrada.
- Indices y unicos: `(cash_session_id, occurred_at)`, `(type, occurred_at)`, `recorded_by`; unico en vinculo de gasto y en `reverses_movement_id` para impedir dos reversas directas del mismo movimiento.
- Decimales: `amount decimal(12,2)`; no usar valores negativos para representar tipo.
- Auditoria: actor/hora/razon y enlace al original; no sobrescribir descripcion.
- Concurrencia: crear dentro de transaccion bloqueando caja abierta; cierre y movimiento no pueden confirmarse simultaneamente sobre el mismo estado.
- Inmutabilidad: no editar ni borrar; corregir con movimiento inverso auditado.
- Soft deletes: no.

### `expenses`

Entra en 2I. Responsabilidad: gasto operativo con `id`, monto, categoria simple, descripcion, metodo, `cash_session_id` nullable, `cash_movement_id` nullable, `recorded_by`, `occurred_at`, estado/anulacion y timestamps. Un gasto en efectivo asociado a caja genera o se vincula de forma unica con una salida de caja para evitar doble conteo.

- Relaciones: pertenece al responsable y opcionalmente a caja/movimiento; el movimiento de efectivo es uno a uno.
- Integridad: monto positivo, descripcion/categoria obligatorias, metodo controlado; efectivo requiere caja abierta y movimiento atomico; anulacion requiere actor, hora y motivo.
- Indices y unicos: fecha/categoria, caja/fecha, responsable y estado; `cash_movement_id` unico nullable.
- Decimales: `amount decimal(12,2)`.
- Auditoria: creador/hora y, al anular/corregir, actor/hora/motivo y movimiento inverso asociado.
- Concurrencia: gasto efectivo y movimiento se crean/revierten en una transaccion con bloqueo de caja.
- Inmutabilidad: los montos confirmados no se editan. La correccion es anulacion mas nuevo gasto, no reemplazo silencioso.
- Soft deletes: no. La eliminacion no borra historia aunque exista el nombre de permiso heredado del roadmap.

### `business_settings`

Decision: puede esperar. No es necesaria para 2D porque el recibo inicial solo muestra la marca conocida y datos reales disponibles. Se evalua despues de entregar direccion, telefono, identidad tributaria y pie.

- Responsabilidad futura: configuracion singleton validada para datos reales de Studio Lemus; no representa empresa, sucursal ni tenant.
- Relaciones: ninguna obligatoria; opcionalmente registra `updated_by` hacia User para auditoria.
- Integridad: una sola fila logica, campos de longitud limitada, telefono/datos tributarios validados solo cuando se conozcan requisitos; nunca usar placeholders como datos legales.
- Indices y unicos: clave singleton unica, por ejemplo `settings_key = studio_lemus`; no necesita indices financieros.
- Decimales: no maneja dinero en el alcance previsto.
- Auditoria: responsable y timestamps; cambios sensibles pueden conservar historial append-only si luego se requieren.
- Concurrencia: actualizacion transaccional/optimista de la unica fila; no afecta el checkout ya guardado.
- Inmutabilidad: los comprobantes historicos no deben regenerar datos empresariales si se decide snapshot; esa decision se toma antes de implementar settings.
- Soft deletes: no; deshabilitar campos o actualizar configuracion validada.

## RBAC futuro

Los permisos se crean solamente en la fase que entrega su funcionalidad. Owner recibe todos los permisos implementados y mantiene Gate bypass; tambien deben sincronizarse para visibilidad. Administrator recibe una lista explicita. Employee recibe solo operacion basica y puede obtener `cash.open`/`cash.close` directamente si el usuario lo aprueba.

Leyenda: `Si` recomendado por defecto; `No` no asignar; `Opcional` asignacion directa y consciente.

| Permiso | Owner | Administrator | Employee | Alcance |
|---|---:|---:|---:|---|
| `cash.access` | Si | Si | Si | Mostrar modulo Caja |
| `cash.view_current` | Si | Si | Si | Ver estado de caja global actual |
| `cash.open` | Si | Si | Opcional | Abrir unica caja |
| `cash.close` | Si | Si | Opcional | Cerrar caja actual |
| `cash.view_history` | Si | Si | No | Consultar cierres dentro de alcance |
| `cash.view_all` | Si | Si | No | Ver cierres sin limite de responsable |
| `cash.create_movement` | Si | Si | No | Crear movimiento auditado en 2I |
| `sales.access` | Si | Si | Si | Mostrar Ventas/Nueva venta |
| `sales.create` | Si | Si | Si | Construir y cobrar venta |
| `sales.view_own` | Si | Si | Si | Ver ventas propias permitidas |
| `sales.view_all` | Si | Si | No | Ver ventas de cualquier usuario |
| `sales.view_detail` | Si | Si | Si | Requiere ademas alcance own/all |
| `sales.reprint` | Si | Si | Si | Requiere ademas alcance de venta |
| `sales.cancel` | Si | Si | No | Anular con motivo; nunca borrar |
| `sales.apply_discount` | Si | Si | No | Descuento fijo y motivo |
| `sales.override_price` | Si | No | No | Reservado; no crear en MVP |
| `reports.sales.view` | Si | No | No | Ingresos brutos de ventas; asignable posteriormente a administrator |
| `reports.cash.view` | Si | Si | No | Cierres/diferencias |
| `reports.profit.view` | Si | No | No | Solo desde 2I y explicitamente concedido |
| `reports.export` | Si | No | No | Fuera de MVP inicial; conceder despues |
| `expenses.view` | Si | Si | No | Ver gastos en 2I |
| `expenses.create` | Si | Si | No | Registrar gasto |
| `expenses.update` | Si | No | No | Preferir correccion auditada, no mutacion libre |
| `expenses.delete` | No | No | No | Nombre reservado; no implementar borrado financiero |

Reglas de autorizacion:

- Cada ruta exige `auth`, `active` y permiso granular; consultas aplican alcance `own` o `all` en backend.
- Tener acceso al selector de servicios de venta no concede `services.view` administrativo.
- Administrator nunca administra owners por permisos financieros.
- Employee con `sales.view_own` solo accede a ventas propias dentro de la politica D-007.
- Ningun permiso sensible se concede automaticamente por existir el rol employee.

## Rutas futuras propuestas

| Metodo | Ruta | Nombre | Accion conceptual | Middleware / permiso | Respuesta | Autorizados |
|---|---|---|---|---|---|---|
| GET | `/` | `home` | Resolver landing; renderizar Inicio owner | `web`, `auth`, `active` | Inertia Home o redirect | Todo activo, destino por rol/permisos |
| GET | `/cash` | `cash.index` | Estado inteligente de caja | `auth`, `active`, `cash.access` | Inertia `Cash/Index` | Owner/admin/employee autorizado |
| POST | `/cash/open` | `cash.open` | Abrir caja global | anterior + `cash.open` | Redirect a current con flash | Permiso explicito |
| GET | `/cash/current` | `cash.current` | Detalle de caja abierta | `auth`, `active`, `cash.view_current` | Inertia `Cash/Current` o redirect a index si cerrada | Permiso explicito |
| POST | `/cash/close` | `cash.close` | Cerrar caja con declaracion ciega | `auth`, `active`, `cash.close` | Redirect a detalle de cierre | Permiso explicito |
| GET | `/sales/new` | `sales.create` | Constructor con servicios/caja | `auth`, `active`, `sales.access`, `sales.create` | Inertia `Sales/Create` | Permiso y caja abierta |
| POST | `/sales` | `sales.store` | Cobrar transaccionalmente | mismos permisos | Redirect/303 a detalle | Permiso y caja abierta |
| GET | `/sales` | `sales.index` | Historial con scope | `auth`, `active`, `sales.access`, own o all | Inertia `Sales/Index` | Segun alcance |
| GET | `/sales/{sale}` | `sales.show` | Detalle | `auth`, `active`, `sales.view_detail` y scope | Inertia `Sales/Show` | Propietario de venta o view all |
| GET | `/sales/{sale}/receipt` | `sales.receipt` | Comprobante imprimible | `auth`, `active`, `sales.reprint` y scope | Inertia/layout print o Blade aislado | Propietario de venta o view all |
| POST | `/sales/{sale}/cancel` | `sales.cancel` | Anulacion auditada | `auth`, `active`, `sales.cancel` | Redirect back con flash | Owner/admin explicito |
| GET | `/cash-closures` | `cash-closures.index` | Historial de cierres | `auth`, `active`, `cash.view_history` | Inertia `CashClosures/Index` | Scope autorizado |
| GET | `/cash-closures/{cashSession}` | `cash-closures.show` | Detalle inmutable | anterior y scope/all | Inertia `CashClosures/Show` | Scope autorizado |
| GET | `/reports` | `reports.index` | Resolver primer reporte autorizado | `auth`, `active`, al menos un permiso reports | Redirect a reporte permitido; 403 sin ninguno | Owner/admin con permiso explicito |
| GET | `/reports/sales` | `reports.sales.index` | Ingresos por periodo/metodo | `auth`, `active`, `reports.sales.view` | Inertia `Reports/Sales` | Owner/admin explicito |
| GET | `/reports/cash` | `reports.cash.index` | Cajas y diferencias | `auth`, `active`, `reports.cash.view` | Inertia `Reports/Cash` | Owner/admin explicito |

Los POST usan CSRF, Form Requests y redireccion 303 de Inertia. Los conflictos de concurrencia/idempotencia devuelven un error de dominio claro (409 o validacion 422 consistente) sin persistencia parcial. No se crea API publica.

## Componentes futuros razonables

| Componente | Responsabilidad | Props principales | Eventos / estado local | Backend y verificaciones |
|---|---|---|---|---|
| `CashStatusCard` | Estado de caja y CTA permitida | session, canOpen, canClose | `open`, `close`, `sell`; sin estado financiero | Session/totales del backend |
| `OpenCashDialog` | Capturar fondo inicial/nota | modelValue, processing, errors | `submit`, `update:modelValue`; formulario local | Backend valida monto, permiso y unica apertura |
| `CloseCashDialog` | Cierre ciego | modelValue, summaryWithoutExpected, processing | `submit`; declarado/nota local | Backend recalcula esperado/diferencia y bloquea caja |
| `ServicePicker` | Buscar y listar activos | services, loading | `add`, busqueda local/debounced | Backend entrega activos y revalida al cobrar |
| `ServiceCard` | Mostrar servicio seleccionable | id, name, duration, formattedPrice, disabled | `add`; sin calculos persistentes | Precio solo informativo |
| `SaleCart` | Orquestar lineas seleccionadas | items, canDiscount | `quantity`, `remove`, `discount`, `checkout` | Totales preliminares; backend recalcula |
| `SaleCartItem` | Linea y cantidad | item, min, max | `increase`, `decrease`, `remove` | No acepta precio editable |
| `SaleSummary` | Subtotal/descuento/total | calculatedPreview, canDiscount | `checkout`, `editDiscount` | Etiqueta como preliminar hasta respuesta |
| `PaymentDialog` | Capturar pago unico MVP | totalPreview, modelValue, processing, errors | `confirm`, `cancel`; metodo/campos local | Backend valida caja, servicios, descuento y pago |
| `PaymentMethodSelector` | Elegir metodo | modelValue, allowedMethods | `update:modelValue` | Lista controlada por backend/config de fase |
| `SaleSuccessDialog` | Confirmacion breve | saleNumber, total, change | `print`, `newSale`, `view` | Usa respuesta confirmada; no vuelve a guardar |
| `ReceiptPreview` | Renderizar recibo interno | sale snapshot, payment, business display data | `print`; sin edicion | Solo datos autorizados del backend |
| `CashSummary` | Totales de caja/cierre | opening, methodTotals, expected?, declared?, difference? | Navegacion; sin calculo autoritativo | Snapshots/calculos backend |
| `MoneyDisplay` | Formato HNL consistente | amount, currency=`HNL`, sign? | Ninguno | Recibe decimal serializado; no calcula negocio |

No crear un store global, motor generico de formularios ni abstracciones de repositorio sin necesidad concreta. El carrito puede permanecer local a `Sales/Create`; solo `sessionStorage` conserva un borrador no autoritativo.

## Pruebas y criterios globales

Cada fase incluye Feature tests Laravel, pruebas unitarias para calculos puros cuando correspondan, `php artisan test`, `npm run build` y pruebas manuales ordenadas. Si SQLite no esta disponible, se informa el bloqueo; no se declara exito. Concurrencia critica debe tener ademas una prueba/integracion MySQL o procedimiento reproducible con solicitudes paralelas.

Cobertura acumulativa obligatoria:

- Redireccion por owner, administrator y employee; destino intended permitido/denegado.
- Navegacion visible por permisos y 403 backend al escribir URLs.
- Apertura y cierre concurrentes; caja atrasada; navegador cerrado no afecta caja.
- Venta atomica sin persistencia parcial.
- Rechazo de precios, descuentos y totales manipulados.
- Servicio inactivo o actualizado durante el cobro.
- Doble confirmacion y reintento con mismo `checkout_token`.
- Numero de venta unico bajo concurrencia.
- Aritmetica decimal, redondeo HNL, pago insuficiente y cambio.
- Anulacion inmutable, motivo, permisos y efecto en caja/reportes.
- Reimpresion con snapshots historicos.
- Alcance own/all para ventas y cierres.
- Reportes por limites horarios de Honduras y exclusion de anuladas.
- Sesion expirada/CSRF durante cobro sin duplicar venta.
- Responsive en 1440x900, 1024x768, 768x1024 y 390x844; sin scroll horizontal; comprobante 80 mm.

## Fases de implementacion

### Fase 2A - Navegacion por rol y correcciones visuales

**Estado:** Aprobada.

**Objetivo:** establecer destinos y navegacion seguros por rol, corregir Configuracion y entregar una pantalla informativa de Caja sin persistencia financiera.

**Alcance:** employee sin Inicio; resolvedor de `/`; navegacion filtrada por permisos/rol; entrada Caja solo cuando su pantalla exista; `Cash/Index` informativa sin montos ni apertura; pantalla 403; ancho de `ConfigurationLayout`; corregir persistencia de `is_active` y sus pruebas porque afecta acceso.

**Fuera de alcance:** permisos financieros, migraciones, apertura real, ventas, pagos, cierres y datos simulados.

**Pantallas:** Login ajustado solo en redireccion, Inicio owner, Acceso denegado, shell de Caja, Configuracion/Usuarios/Servicios.

**Backend:** resolvedor de landing; autorizacion explicita para Home owner; ruta/pagina Caja sin consultar tablas futuras; conservar `intended` solo si es autorizado; corregir mutacion de estado activo de usuarios sin ampliar CRUD.

**Frontend:** actualizar `AppLayout` para ocultar Inicio a employee y no mostrar opciones futuras; crear pagina informativa Caja; corregir columnas/tabs de `ConfigurationLayout`; evitar doble sidebar y truncamiento.

**Migraciones conceptuales:** ninguna.

**Permisos:** no crear permisos de Caja. La pantalla shell se limita temporalmente por rol/flujo autenticado documentado; 2B reemplaza esta regla por `cash.access`.

**Pruebas automaticas:** Feature tests de landing de los tres roles, intended autorizado/denegado, acceso manual a Configuracion/reportes, owner dashboard, usuario inactivo y persistencia real de activacion; prueba de props Inertia de navegacion/pagina; ejecutar suite completa y build.

**Pruebas manuales:** iniciar con owner/admin/employee; comprobar destinos; escribir cada URL restringida; verificar menu; medir Configuracion en 1440x900, 1024x768, 768x1024 y 390x844; confirmar textos completos, tabs, estado activo y ausencia de scroll horizontal.

**Criterios de aceptacion:** employee nunca usa Inicio como destino ni enlace; backend devuelve 403 donde corresponde; solo aparece Caja porque ya tiene pagina util; Configuracion no trunca; activacion/inactivacion persiste; suite y build correctos.

**Dependencias:** Fase 1B aprobada y D-002 aprobada.

**Riesgos:** regla temporal de acceso a Caja; diferencia entre Gate owner y permisos visibles; breakpoints de Vuetify; defecto preexistente de `is_active`.

**Archivos probables:** `routes/web.php`, `AuthController.php` o nuevo resolvedor, `HomeController.php`, `User.php`/`UserController.php`, `AppLayout.vue`, `ConfigurationLayout.vue`, nueva `Pages/Cash/Index.vue`, pagina 403/manejo de errores y tests Feature. El alcance exacto debe decidirse tras reinspeccion.

**Condicion para avanzar:** estado `En pruebas`, todas las pruebas automaticas/build correctos y confirmacion manual del usuario para marcar 2A `Aprobada`.

**Resultado de implementacion 2026-07-18:** D-002 aprobada y aplicada; resolvedor centralizado por rol; `/cash` informativa y autorizada sin persistencia; Inicio exclusivo de owner; 403 Inertia en espanol; navegacion de Configuracion lateral desde desktop y tabs en tablet/movil; `is_active` persistente. No hubo migraciones ni permisos nuevos. `php artisan optimize:clear` correcto. Suite completa: 26 pruebas y 109 aserciones correctas cargando temporalmente `pdo_sqlite`/`sqlite3`; el PHP global aun no carga esas extensiones por defecto. `npm run build` correcto con advertencia no bloqueante por tamano de bundle. Pruebas manuales pendientes de confirmacion del usuario; por eso la aprobacion permanece `No` y 2B continua `Pendiente`.

## Incidencia de estabilización

**Síntoma:** después de autenticar, `/`, `/cash` y las pantallas de Configuración recibían HTML y datos Inertia válidos, pero el contenido quedaba completamente blanco. `/login` sí montaba correctamente.

**Causa exacta:** `AppServiceProvider` compartía `auth.user` como una instancia de `UserResource`. Al serializarse como prop compartida, el navegador recibía `{ data: { name, email, ... } }`. `AppLayout` enviaba ese wrapper a `UserMenu`, que esperaba un usuario plano y ejecutaba `props.user.name.split(...)`. Como `name` era `undefined`, Vue lanzaba `TypeError: Cannot read properties of undefined (reading 'split')` durante el render y desmontaba el layout autenticado.

**Archivos afectados:** causa y corrección en `app/Providers/AppServiceProvider.php`; excepción observada en `resources/js/Components/UserMenu.vue`; contrato protegido por `tests/Feature/Phase2ANavigationTest.php`; evidencia visual en `docs/screenshots/phase-2a-stabilization/`.

**Corrección:** resolver `UserResource` antes de compartirlo con Inertia para entregar `auth.user` como objeto plano. No se desactivó middleware, RBAC ni navegación por rol. Después de detener Vite se eliminó el `public/hot` residual para que Laravel use el build de producción. No se agregó ni eliminó código PWA porque el repositorio no registra service workers.

**Pruebas agregadas:** contrato `auth.user.name` sin wrapper `data`; invitado redirigido desde `/` y `/cash`; owner renderiza Inicio y Caja sin ciclos; administrator llega a su primera pantalla permitida y sin permisos administrativos cae en Caja/recibe 403 en Configuración; employee redirige una sola vez a Caja y recibe 403 en las tres rutas de Configuración; persistencia y bloqueo de `is_active`.

**Resultado:** `php artisan optimize:clear` y `php artisan route:list` correctos. El `php artisan test` directo sigue fallando por `could not find driver` porque SQLite está deshabilitado globalmente; cargando temporalmente `pdo_sqlite` y `sqlite3`, 28 pruebas y 137 aserciones pasan. `npm run build` pasa con advertencia no bloqueante por tamaño de bundle. `npm run dev` inicia en `http://127.0.0.1:5173` sin error de compilación/HMR y fue detenido. Chrome limpio validó modo desarrollo y producción: componentes `Auth/Login`, `Home`, `Cash/Index`, `Configuration/Users` y `Configuration/Services`, `data-page` correcto, Vue montado, assets 200, sin fallos de red ni excepciones JavaScript. Se generaron 12 capturas en 1440x900, 1024x768, 768x1024 y 390x844.

**Service worker:** no existen `sw.js`, `service-worker`, `registerSW`, `virtual:pwa-register`, `vite-plugin-pwa` ni `navigator.serviceWorker` en el código del proyecto. `/sw.js` responde 404 y un perfil limpio reportó cero registros/controladores. La solicitud observada pertenece a un registro residual del navegador para ese origen. Si persiste, eliminarlo en DevTools > Application > Service Workers > Unregister, luego Application > Storage > Clear site data para `http://127.0.0.1:8000` y recargar forzadamente. No se modificó código ajeno para ocultarlo.

**Aprobacion:** implementacion visual y funcional validada expresamente por el usuario el 2026-07-19. Fase 2A `Aprobada`, aprobacion `Si`.

**Riesgos residuales:** SQLite continúa deshabilitado por defecto y el bundle supera 500 kB. Estos riesgos no bloquean la aprobacion funcional de 2A.

### Fase 2B - Apertura y caja actual

**Estado:** Sustituida.

**Aprobacion:** No aplica.

**Motivo de sustitucion:** cambio aprobado hacia POS simplificado. La implementacion se conserva como legado tecnico sin uso operativo y no condiciona las ventas.

**Decisiones aprobadas para esta fase (2026-07-19):**

- D-001: una unica sesion global de caja por turno operativo. Solo puede existir una abierta en toda la plataforma; no pertenece a navegador, dispositivo ni usuario; registra quien abre y, en una fase posterior, quien cierra. Cambiar de usuario o cerrar el navegador no la cierra. No se cierra automaticamente. Una caja de un dia anterior se muestra atrasada, bloquea otra apertura y conserva su fecha. Las aperturas concurrentes se protegen mediante transaccion y restriccion real de base de datos.
- D-011: timestamps almacenados en UTC; visualizacion, dia operativo y deteccion de atraso en `America/Tegucigalpa`. No se guardan manualmente horas locales como UTC ni se cambia destructivamente la zona horaria de la base.
- D-012: administrator recibe solo permisos operativos entregados en cada fase. En 2B, owner y administrator reciben `cash.access`, `cash.view_current` y `cash.open`; employee recibe `cash.access` y `cash.view_current`, pero no `cash.open` por defecto. No se conceden capacidades de owner, ganancias, exportaciones, configuracion adicional ni funciones inexistentes.

**Objetivo:** persistir una unica sesion global de caja, abrirla de forma concurrentemente segura y consultar su estado; sin ventas.

**Alcance:** permisos de Caja implementados para funciones reales; `cash_sessions`; apertura con monto/nota; caja cerrada/actual/atrasada; responsables y timestamps; una sola abierta; acciones segun permiso.

**Fuera de alcance:** constructor, cobros, movimientos, cierre operativo y reportes. Hasta 2G, esta fase no debe desplegarse como ciclo productivo irreversible; sus pruebas usan transacciones/base controlada.

**Pantallas:** Caja combina estado cerrado, `OpenCashDialog` y estado actual. Employee sin `cash.open` recibe mensaje para solicitar apertura.

**Backend:** modelo, relaciones User, Form Requests, Resource y controlador minimo; transaccion de apertura, guardia unica `active_guard`, manejo de conflicto, deteccion de caja del dia anterior y consulta actual.

**Frontend:** `Cash/Index` presenta estado cerrado, apertura autorizada, estado actual y alerta de atraso; `OpenCashDialog` encapsula fondo/nota, errores, procesamiento y prevencion de doble envio. Nueva venta y cierre permanecen ausentes.

**Migraciones conceptuales:** crear `cash_sessions` con todos los campos de apertura/cierre previstos y restriccion unica de sesion activa; migracion reversible sin tocar migraciones existentes.

**Permisos:** crear solo `cash.access`, `cash.view_current`, `cash.open`; sincronizar owner, asignar administrator explicitamente y no asignar `cash.open` a employee por defecto. `cash.close` se crea en 2G cuando exista cierre.

**Pruebas automaticas:** apertura autorizada/no autorizada, monto decimal, unica caja, dos solicitudes concurrentes o prueba MySQL equivalente, estado persistente tras nueva sesion web, caja atrasada, visibilidad employee, rollback ante error; suite/build.

**Pruebas manuales:** abrir como owner/admin; comprobar monto/nota; cerrar navegador y volver; entrar como employee; intentar segunda apertura en dos navegadores; simular caja de fecha anterior en entorno de prueba controlado; revisar responsive.

**Criterios de aceptacion:** nunca existen dos cajas abiertas; el navegador no gobierna estado; actor/hora/monto son correctos; errores son claros; no hay ventas ni cierres ficticios.

**Dependencias:** 2A aprobada; D-001, D-002 y D-012 aprobadas; MySQL disponible para prueba de concurrencia.

**Riesgos:** SQLite no reproduce la carrera de MySQL y continua deshabilitado en el PHP global; la prueba MySQL es opt-in sobre una base dedicada. La caja abierta persiste y todavia no existe cierre, por lo que no debe usarse como ciclo productivo hasta 2G. El bundle frontend mantiene la advertencia no bloqueante por superar 500 kB.

**Archivos probables:** migracion nueva, `CashSession.php`, relaciones en `User.php`, requests/resource/controlador de Cash, `Permissions.php`, seeders de permisos/roles, `routes/web.php`, `Pages/Cash/*`, componentes Cash y tests.

**Resultado de implementacion 2026-07-19:**

- Migracion real `2026_07_19_120000_create_cash_sessions_table.php`, aplicada en batch 2 mediante `php artisan migrate`. Crea los 15 campos previstos, FKs `restrict`, decimales `decimal(12,2)`, estados `open`/`closed`, indices de responsables/fechas/estado y `cash_sessions_active_guard_unique`. No se modificaron migraciones anteriores, no se usaron soft deletes y no se ejecuto `migrate:fresh`.
- `active_guard` es nullable para el cierre futuro, usa `OPEN` mientras la sesion esta abierta y posee un indice unico real. `OpenCashSessionAction` verifica antes y dentro de la transaccion; una violacion unica se traduce solo cuando el driver confirma ese indice (o la columna equivalente en SQLite). Otros errores de base de datos no se ocultan.
- `CashSession` esta totalmente protegido contra asignacion masiva, castea fechas y decimales, define estados/guardia/timezone, relaciones `openedBy`/`closedBy`, scope `currentlyOpen` y deteccion de atraso con `America/Tegucigalpa`. `User` expone las relaciones inversas. La conexion MySQL se fija a `+00:00`; las fechas se muestran en hora de Honduras.
- Permisos creados: `cash.access`, `cash.view_current` y `cash.open`. Owner y administrator reciben los tres. Employee recibe solamente acceso y consulta. Los seeders se ejecutaron repetidamente en pruebas sin duplicados y el owner conserva permisos reales ademas de `Gate::before`.
- Rutas reales: `GET /cash` requiere `auth`, `active`, `cash.access` y `cash.view_current`; `POST /cash/open` requiere `auth`, `active`, `cash.access` y `cash.open`. No existen rutas de cierre ni ventas.
- `CashSessionResource` expone solo identificador, estado, fondo, apertura UTC/display Honduras, atraso, nota y datos minimos de quien abrio. Nunca expone `active_guard` ni campos financieros de cierre.
- Interfaz: owner/administrator ven `Abrir caja` cuando esta cerrada; employee recibe la instruccion de solicitar apertura sin boton. El dialogo es fullscreen en movil, usa HNL, valida junto a cada campo, bloquea doble envio y no se cierra externamente mientras procesa. La caja abierta muestra actor, fecha/hora Honduras, fondo, nota, persistencia y alerta exacta de atraso, sin ventas, cierre ni totales simulados.
- Pruebas: el `php artisan test` directo fallo, como estaba previsto, por `could not find driver` al no cargar SQLite: 1 prueba paso y 47 terminaron con error de conexion. Con un INI temporal que cargo `pdo_sqlite` y `sqlite3`, la suite completa paso: 48 pruebas, 250 aserciones. El archivo temporal se elimino al finalizar.
- Concurrencia MySQL: `tests/Integration/verify_cash_open_concurrency.php` se ejecuto sobre la base dedicada `studio_lemus_cash_concurrency_test`. Dos procesos sincronizados produjeron exactamente `opened` y `conflict` con `Ya existe una caja abierta`; la consulta final conto una sola caja abierta y confirmo `cash_sessions_active_guard_unique`. El script elimino solo su caja/usuario marcados; se confirmaron cero residuos y despues se elimino la base dedicada.
- Procedimiento reproducible de concurrencia: crear una base vacia terminada en `_cash_concurrency_test`; definir temporalmente `APP_ENV=testing`, `DB_DATABASE`, `CASH_CONCURRENCY_DATABASE` y `CASH_CONCURRENCY_CONFIRM=DEDICATED_MYSQL_DATABASE_ONLY`; ejecutar `php artisan migrate --force`, `php artisan db:seed --force` y `php tests/Integration/verify_cash_open_concurrency.php`. El script aborta ante driver, entorno, nombre de base, indice o caja previa incorrectos y nunca limpia filas ajenas a su marcador.
- Build: `npm run build` correcto, 1,178 modulos transformados; advertencia no bloqueante por chunks mayores de 500 kB.
- Pruebas manuales pendientes: owner abre, recarga, cierra sesion y confirma persistencia; administrator abre cuando no existe y recibe conflicto claro cuando existe; employee ve el estado, no ve apertura y recibe 403 al fabricar el POST; validar caja atrasada; revisar 1440x900, 1024x768, 768x1024 y 390x844 sin scroll horizontal y con dialogo/alertas usables.
- Reinicio manual seguro solo en desarrollo: localizar explicitamente la caja mediante `php artisan tinker`, comprobar que no tenga ventas relacionadas si ya existe la tabla `sales` y eliminar solo ese ID. No usar el procedimiento sobre informacion real, no borrar por estado de forma masiva y no hacerlo cuando existan ventas. No se agrego ruta, boton ni seeder de reseteo.

**Condicion para avanzar:** 2B aprobada por usuario despues de pruebas manuales y evidencia de apertura concurrente segura.

### Fase 2C - Constructor de Nueva venta

**Estado:** Pendiente.

**Objetivo:** construir un carrito responsive con servicios activos y totales preliminares, sin guardar ni cobrar.

**Alcance:** ruta Nueva venta; acceso solo con caja abierta; busqueda; selector; varios servicios; cantidades; retiro; subtotal/total preliminares; borrador en `sessionStorage`; navegacion operativa.

**Fuera de alcance:** tablas de ventas, POST de cobro, metodos de pago, descuentos funcionales, numeracion, comprobante y cierre.

**Pantallas:** `/sales/new`, selector, carrito, resumen y estado sin servicios/caja cerrada.

**Backend:** controlador de pagina que verifica caja y entrega servicios activos con ID, nombre, descripcion, duracion, precio y `updated_at`; no acepta persistencia de ventas.

**Frontend:** `ServicePicker`, `ServiceCard`, `SaleCart`, `SaleCartItem`, `SaleSummary`, `MoneyDisplay`; layout de dos areas, adaptacion tablet y bottom sheet/resumen movil; Cobrar se muestra como siguiente paso no operativo claramente bloqueado o se omite hasta 2D, sin enlace falso.

**Migraciones conceptuales:** ninguna.

**Permisos:** crear `sales.access` y `sales.create`; owner y administrator si D-012 lo aprueba; employee recibe ambos por defecto. El endpoint de servicios de venta no requiere ni concede `services.view`.

**Pruebas automaticas:** acceso con/sin caja, permisos, solo activos, datos minimos, employee sin acceso administrativo, pagina Inertia y precision de serializacion; suite/build. Los calculos locales se prueban si se extraen a una funcion pura.

**Pruebas manuales:** buscar, agregar varios, cambiar cantidades, retirar, refrescar/restaurar, cambiar usuario/caja y descartar borrador incompatible; revisar desktop/tablet/movil, teclas y tactil.

**Criterios de aceptacion:** constructor rapido y sin scroll horizontal; no muestra inactivos; no persiste ventas; precio no es editable; carrito sobreviviente se revalida al cargar.

**Dependencias:** 2B aprobada y D-003 aprobada.

**Riesgos:** tratar preview como definitivo; exponer CRUD de Servicios; estado local obsoleto; CTA Cobrar no funcional. Debe omitirse o etiquetarse sin accion hasta 2D, sin entrada de navegacion falsa.

**Archivos probables:** `routes/web.php`, controlador/resource especifico del constructor, permisos/seeders, `AppLayout.vue`, nueva `Pages/Sales/Create.vue`, componentes de venta, estilos y tests.

**Condicion para avanzar:** 2C aprobada despues de validar carrito y responsive en los cuatro tamanos.

### Fase 2D - Cobro y comprobante

**Estado:** Pendiente.

**Objetivo:** confirmar una venta atomica, registrar un pago MVP, generar numero y ofrecer comprobante interno imprimible.

**Alcance:** `sales`, `sale_items`, `payments`; efectivo/tarjeta/transferencia; descuento fijo autorizado; backend autoritativo; idempotencia; resultado; vista 80 mm; impresion/reimpresion directa de la venta recien creada.

**Fuera de alcance:** pago mixto, precio libre, conceptos manuales, cortesias, historial completo, anulacion, cierre, factura fiscal, PDF e integracion de pasarela.

**Pantallas:** Nueva venta habilita cobro; `PaymentDialog`; resultado exitoso/detalle basico; `/sales/{sale}/receipt`.

**Backend:** Form Request; servicio/accion transaccional enfocado en checkout; bloqueo de caja; recarga de servicios; calculo decimal; snapshot; token idempotente; numero `SL-{ID}`; pago; Resource de resultado y autorizacion de comprobante.

**Frontend:** selector de metodo, campos condicionales, procesamiento no cancelable accidentalmente, conflictos recuperables, resultado, `ReceiptPreview`, CSS print 80 mm y nueva pestana.

**Migraciones conceptuales:** tablas `sales`, `sale_items`, `payments` con FKs, indices, unicos, decimales y sin soft deletes. Agregar `discount_reason` y `checkout_token` segun modelo conceptual.

**Permisos:** crear `sales.view_detail`, `sales.reprint`, `sales.apply_discount`. Asignar detalle/reimpresion a los tres roles segun scope futuro; descuento a owner/admin, no employee. No crear `sales.override_price`.

**Pruebas automaticas:** transaccion/rollback; montos y precios manipulados; inactivo/cambio de precio; caja cerrada; efectivo insuficiente/cambio; referencias; descuento autorizado/no autorizado; doble envio/token; dos usuarios; numero unico; snapshots; receipt autorizado; sesion expirada donde sea automatizable; unit tests de calculos; suite/build.

**Pruebas manuales:** cobrar con cada metodo; efectivo exacto/mayor/insuficiente; doble clic; desconectar/reintentar; desactivar o cambiar servicio en otra sesion; expirar sesion; imprimir 80 mm y reimprimir tras fallo de impresora; verificar nueva pestana y Otra venta.

**Criterios de aceptacion:** una confirmacion produce exactamente una venta completa; ninguna persistencia parcial; totales backend correctos; comprobante no fiscal, legible y reimprimible; imprimir no altera estado.

**Dependencias:** 2C aprobada; D-004, D-005, D-009, D-010 y D-011 aprobadas.

**Riesgos:** aritmetica PHP/SQL/JS, bloqueo excesivo, token reutilizado mal, datos sensibles, impresion del navegador y timezone.

**Archivos probables:** tres migraciones o migracion ordenada equivalente, modelos/relaciones, requests/resources, accion transaccional/controladores, rutas, componentes Payment/Success/Receipt/Money, paginas Sale/Receipt, CSS print, permisos/seeders y tests.

**Condicion para avanzar:** 2D aprobada tras cobros manuales de los tres metodos, pruebas de idempotencia y comprobante fisico/preview 80 mm.

### Fase 2E - Historial, detalle y reimpresion

**Estado:** Pendiente.

**Objetivo:** consultar ventas confirmadas con alcance seguro, detalle historico y reimpresion.

**Alcance:** listado paginado/filtrado; scope own/all; ultimas ventas propias; detalle completo; enlaces al mismo recibo; estados de carga/vacio/error.

**Fuera de alcance:** anulacion, edicion, exportacion, reportes agregados y ventas de otros para employee.

**Pantallas:** Historial, Detalle, Comprobantes recientes integrados en Historial/Caja y Reimpresion.

**Backend:** consultas paginadas con eager loading controlado; filtros de fecha/numero/metodo/estado; autorizacion por scope en cada show/receipt; Resources que no exponen costos.

**Frontend:** tabla desktop/cards movil, filtros, chips de estado, detalle por secciones y accion reimprimir.

**Migraciones conceptuales:** ninguna salvo indice adicional demostrado por consulta real; no cambiar snapshots.

**Permisos:** crear `sales.view_own` y `sales.view_all`; employee solo own, administrator/owner all. `sales.view_detail`/`reprint` siempre requieren tambien scope.

**Pruebas automaticas:** own/all, 403 por ID manual, paginacion/filtros, employee sin datos ajenos, snapshot tras editar/eliminar servicio, reimpresion, consultas razonables; suite/build.

**Pruebas manuales:** ventas de dos usuarios; filtros; URL directa ajena; editar/eliminar servicio y reimprimir igual; vacios/carga; responsive.

**Criterios de aceptacion:** employee solo ve ultimas ventas propias segun D-007; owner/admin autorizados ven todas; receipt conserva historia; no existe accion editar/eliminar.

**Dependencias:** 2D aprobada y D-007 aprobada.

**Riesgos:** fuga de datos por binding sin scope, N+1, filtros de timezone y exposicion de referencias completas.

**Archivos probables:** rutas/controladores/resources/queries de Sales, `Pages/Sales/Index.vue`, `Show.vue`, componentes de listado/detalle, permisos/seeders y tests.

**Condicion para avanzar:** 2E aprobada despues de pruebas cruzadas entre usuarios y reimpresion historica.

### Fase 2F - Anulacion de ventas

**Estado:** Pendiente.

**Objetivo:** anular sin borrar, con motivo, responsable, tiempo y efecto contable verificable mientras la caja este abierta.

**Alcance:** transicion completed a canceled; permiso; motivo; confirmacion; auditoria; exclusiones en totales; restriccion de caja cerrada; nota de reversa externa para pagos no efectivos.

**Fuera de alcance:** reactivar, borrar, editar items/pagos, anular caja cerrada, devoluciones parciales y reembolsos de pasarela.

**Pantallas:** accion y dialogo en Detalle de venta; estado/anulacion visible en Historial, Detalle y Comprobante marcado como anulado.

**Backend:** request y accion transaccional; `lockForUpdate` en venta/caja; validar completed y caja open; guardar actor/hora/motivo; consultas financieras excluyen canceled sin borrar pagos.

**Frontend:** confirmacion destructiva clara, motivo obligatorio, aviso para tarjeta/transferencia, estado procesando y ocultar accion sin permiso.

**Migraciones conceptuales:** campos de anulacion ya creados en 2D; solo agregar indice si falto. No tabla de borrados.

**Permisos:** crear `sales.cancel`; owner y administrator recomendado, employee no. Backend obligatorio aunque no haya boton.

**Pruebas automaticas:** permisos; motivo; doble anulacion concurrente; caja cerrada; efecto en efectivo esperado/ingresos; no reactivacion; datos conservados; pago no borrado; receipt anulado; suite/build.

**Pruebas manuales:** anular efectivo y no efectivo; comprobar advertencia/referencia; intentar como employee; intentar dos veces; cerrar caja en escenario controlado e intentar; revisar historial y recibo.

**Criterios de aceptacion:** ninguna venta se elimina; auditoria completa; una anulacion valida ocurre una vez; totales dejan de contarla y la historia sigue visible.

**Dependencias:** 2E aprobada y D-008 aprobada.

**Riesgos:** confundir anulacion administrativa con reembolso real, carreras con cierre y reportes que omitan el filtro de estado.

**Archivos probables:** ruta/controlador/request/accion de cancelacion, modelo/resource Sale, detalle/listado/receipt, permiso/seeder, calculos compartidos y tests.

**Condicion para avanzar:** 2F aprobada con evidencia de efecto correcto en efectivo e ingresos y denegacion al employee.

### Fase 2G - Cierre de caja

**Estado:** Pendiente.

**Objetivo:** cerrar ciegamente la caja, congelar totales y ofrecer historial/detalle inmutables.

**Alcance:** `cash.close`, totales por metodo, esperado de efectivo, declarado, diferencia, nota, responsable/hora, concurrencia, caja atrasada, listado y detalle.

**Fuera de alcance:** movimientos/entradas/salidas/gastos, reapertura, edicion, cierre automatico, ganancias y exportacion.

**Pantallas:** Caja actual con `CloseCashDialog`, resultado de cierre, Cierres y Detalle de cierre.

**Backend:** calculador de cierre con decimales; transaccion y bloqueo de caja; agregar ventas/pagos completed; snapshot de expected/declared/difference; validar nota si diferencia; consultas de historial con scope.

**Frontend:** no mostrar esperado antes de declarar; mostrar ventas por metodo sin revelar efectivo esperado si contradice cierre ciego; despues del cierre mostrar resumen completo; tabla/cards de cierres.

**Migraciones conceptuales:** campos ya previstos en `cash_sessions`; crear mecanismo append-only de nota correctiva solo si D-001 lo aprueba con alcance exacto. No editar columnas cerradas.

**Permisos:** crear `cash.close`, `cash.view_history`, `cash.view_all`; owner/admin segun matriz; employee solo `cash.close` opcional y sin history/all por defecto.

**Pruebas automaticas:** formula, anuladas, metodos, diferencia positiva/negativa/cero, nota, dos cierres concurrentes, apertura bloqueada hasta cierre, inmutabilidad, permisos/scopes, fechas; unit tests del calculador; suite/build y comprobacion MySQL.

**Pruebas manuales:** contar efectivo sin ver esperado; cerrar exacto/con diferencia; doble navegador; employee con/sin permiso; caja atrasada; historial/detalle responsive; intentar editar/reabrir.

**Criterios de aceptacion:** primer cierre gana, segundo no altera; snapshot coincide; caja cerrada es inmutable; se puede abrir nueva caja despues; diferencia y nota son claras.

**Dependencias:** 2F aprobada y D-006 aprobada.

**Riesgos:** cierre sin movimientos aun, sesgo si UI filtra esperado, anulacion simultanea, limites de dia y correcciones posteriores.

**Archivos probables:** controlador/request/accion/calculador de cierre, modelo/resource CashSession, rutas, paginas CashClosures, componentes Close/CashSummary, permisos/seeders y tests.

**Condicion para avanzar:** 2G aprobada tras ciclo completo abrir-vender-anular-cerrar y prueba concurrente en MySQL.

### Fase 2H - Reportes de ingresos

**Estado:** Pendiente.

**Objetivo:** mostrar ingresos cobrados diarios, semanales y mensuales, ventas, metodos y cierres sin afirmar ganancias.

**Alcance:** filtros por periodo local; conteo/total de completed; anulaciones separadas; metodos; cierres/diferencias; drill-down autorizado; estados vacios/carga.

**Fuera de alcance:** costos, gastos, ganancia bruta/neta, exportacion inicial, graficos complejos y pronosticos.

**Pantallas:** Reportes de ingresos unifica Resumen diario, semanal y mensual; reporte de caja puede ser tab/seccion separada.

**Backend:** queries agregadas con limites `America/Tegucigalpa` convertidos a UTC; filtros autorizados; decimales SQL; Resources; cache solo si se demuestra necesidad y sin datos obsoletos tras venta/anulacion/cierre.

**Frontend:** metricas tituladas Ingresos, Ventas cobradas, Anulaciones y Metodos; filtros simples; tabla/lista por dia; ayudas de formula y moneda.

**Migraciones conceptuales:** ninguna salvo indices demostrados por `EXPLAIN`; no crear tablas de reportes.

**Permisos:** crear `reports.sales.view` y `reports.cash.view`; owner y administrator recomendado; employee no. `reports.export` no se crea aun.

**Pruebas automaticas:** limites dia/semana/mes, UTC-Honduras, completed/canceled, metodos, decimales, permisos, datos de dos usuarios/cajas y vacios; suite/build.

**Pruebas manuales:** cambiar periodos y fechas limite; comparar con ventas/cierres conocidos; anular y verificar; probar employee/administrator; desktop/movil.

**Criterios de aceptacion:** cifras reconciliables con detalle, labels nunca dicen ganancia, timezone visible, anuladas no inflan ingresos y employee recibe 403.

**Dependencias:** 2G aprobada y D-011 aprobada.

**Riesgos:** intervalos horarios, doble conteo por joins de items/pagos, rendimiento y lenguaje financiero enganoso.

**Archivos probables:** rutas/controladores/query objects o acciones de reporte, resources, `Pages/Reports/Sales.vue`, componentes de metricas/filtros, permisos/seeders, indices justificados y tests.

**Condicion para avanzar:** 2H aprobada despues de reconciliacion manual diaria/semanal/mensual.

### Fase 2I - Gastos, costos y ganancias

**Estado:** Pendiente.

**Objetivo:** completar las entradas necesarias para calcular costos, gastos, ganancia bruta y ganancia neta sin alterar historia.

**Alcance:** costo estimado por servicio con acceso restringido; snapshot de costo en nuevos items; gastos; movimientos de caja; formulas; reportes de ganancia; correcciones auditadas.

**Fuera de alcance:** inventario, compras/proveedores complejos, nomina, comisiones, contabilidad fiscal, costos retroactivos inventados y multiempresa.

**Pantallas:** costo en Servicios solo autorizado; Gastos simple; movimiento de caja; Caja actual/cierre ampliado; Reporte de ganancias con formula.

**Backend:** modelos/requests/resources de Expense/CashMovement; transacciones para gasto efectivo y movimiento vinculado; snapshots de costo; calculadores bruta/neta; anulacion/reversa en vez de delete; scopes financieros.

**Frontend:** formularios simples, costos ocultos a employee, movimientos con razon, reportes que separan ingresos, costo, gastos, bruta y neta; advertir datos incompletos.

**Migraciones conceptuales:** costo nullable en services; costo snapshot nullable en sale_items; tablas expenses y cash_movements con vinculo unico cuando corresponda; indices/decimales/auditoria. Datos anteriores sin costo permanecen `unknown`, nunca cero inventado.

**Permisos:** crear `cash.create_movement`, `expenses.view/create/update` segun politica auditada, `reports.profit.view`; no implementar delete fisico ni conceder profit a administrator/employee por defecto. `reports.export` queda para aprobacion separada.

**Pruebas automaticas:** confidencialidad de costos; snapshot; venta anterior sin costo; gasto efectivo contado una vez; movimientos y cierre; reversas; formulas bruta/neta; anulaciones; periodos; permisos; unit tests decimales; suite/build.

**Pruebas manuales:** asignar/cambiar costo y comprobar snapshots; registrar gasto por metodos; movimiento entrada/salida; cerrar caja; reconciliar formulas; probar roles; datos incompletos y responsive.

**Criterios de aceptacion:** no se muestra ganancia si faltan bases relevantes sin advertencia; bruta/neta reconciliables; costos historicos no cambian; employee no ve costos/reportes; caja incorpora movimientos exactamente una vez.

**Dependencias:** 2H aprobada y decisiones adicionales aprobadas sobre categorias de gasto, correcciones, datos incompletos y alcance de ganancia neta.

**Riesgos:** historico sin costo, doble conteo gasto/movimiento, revelar margen, usar delete nominal, definir neta sin todas las salidas.

**Archivos probables:** nuevas migraciones/modelos/controllers/requests/resources para gastos/movimientos, Service/SaleItem y formularios, calculadores/reportes, rutas, componentes/paginas, permisos/seeders y tests.

**Condicion para finalizar 2I:** aprobacion manual despues de reconciliar un ciclo completo y confirmar formulas/documentacion. Cualquier pago mixto, PDF, exportacion o fiscalidad requiere un plan posterior independiente.

## Registro de decisiones

Todas las entradas inician como propuestas. Solo las marcadas `Aprobada` pueden utilizarse para implementar una fase dependiente.

| ID | Fecha | Tema | Decision | Motivo | Alternativas descartadas | Consecuencias | Estado |
|---|---|---|---|---|---|---|---|
| D-001 | 2026-07-18 | Estrategia de caja | Una sesion global por turno, maximo una abierta | Coincide con una caja fisica y evita saldos fragmentados | Por usuario, dispositivo o varias por turno | No pertenece a usuario, navegador ni dispositivo; persiste hasta cierre manual; caja anterior se marca atrasada; requiere transaccion y guardia unica de base de datos | Aprobada |
| D-002 | 2026-07-18 | Ruta inicial por rol | Owner inicia en Inicio; administrator inicia en la primera pantalla permitida; employee no ve ni usa Inicio, entra a Nueva venta con caja abierta y a Caja sin caja. En 2A, al no existir persistencia de Caja, employee va a `/cash`. | Reduce pasos, evita destinos sin permiso y mantiene la experiencia employee enfocada en operacion | Inicio para todos; ruta fija sin revisar permisos; redirecciones solo frontend | Requiere resolvedor centralizado, autorizacion backend de rutas manuales, validacion de `intended` y prevencion de ciclos | Aprobada |
| D-003 | 2026-07-18 | Pagina vs modal | Pagina dedicada para carrito, modal solo de cobro y vista separada de recibo | Mejor espacio, responsive, recuperacion e idempotencia | Toda venta modal; todo en una sola pagina | Tres estados claros y componentes pequenos | Propuesta |
| D-004 | 2026-07-18 | Metodos MVP | Efectivo, tarjeta y transferencia; exactamente un payment; mixto posterior | Cubre operacion comun sin complejidad de conciliacion | Solo efectivo; mixto desde inicio | Esquema admite varios, UI/API restringen uno | Propuesta |
| D-005 | 2026-07-18 | Comprobante | Recibo interno HTML 80 mm, pestana independiente e impresion del navegador | Simple, confiable, sin dependencia ni requisito fiscal inventado | PDF, modal, factura fiscal | Impresion no revierte venta; datos legales esperan validacion | Propuesta |
| D-006 | 2026-07-18 | Tipo de cierre | Cierre ciego: declarar antes de revelar esperado/diferencia | Reduce sesgo y mejora control | Cierre visible | UI oculta esperado hasta confirmar; nota si diferencia | Propuesta |
| D-007 | 2026-07-18 | Historial employee | Ultimas 20 ventas propias de la caja actual; resto solo con permiso superior | Permite reimpresion operativa sin exponer finanzas ajenas | Todo historial propio; todo el historial de caja | Scopes backend estrictos y 403 por URL ajena | Propuesta |
| D-008 | 2026-07-18 | Anulaciones | Solo completed de caja abierta, motivo/actor/hora, sin reactivar; owner/admin con permiso | Conserva auditoria y permite ajustar cierre actual | Borrar; editar; anular despues de cierre sin modelo de devolucion | Pagos permanecen; caja/reportes excluyen; no reembolso automatico | Propuesta |
| D-009 | 2026-07-18 | Descuentos/precios | Descuento fijo por venta con permiso y motivo; sin override, manuales ni cortesias | Control simple sin permitir manipulacion libre | Ningun descuento; descuentos amplios por linea/porcentaje | Campos de descuento; employee sin permiso; backend recalcula | Propuesta |
| D-010 | 2026-07-18 | Numeracion | `SL-` + ID global con padding, unica, inmutable y sin reinicio diario | Evita contador concurrente y es legible | Secuencia diaria; UUID visible; numero fiscal | Puede haber saltos; no implica numeracion fiscal | Propuesta |
| D-011 | 2026-07-18 | Zona horaria | Guardar timestamps en UTC y mostrar/definir el dia operativo con `America/Tegucigalpa` | Evita limites diarios ambiguos y conserva timestamps consistentes | Cambiar almacenamiento a hora local; tratar UTC como hora visual | Queries convierten limites locales a UTC; no cambiar destructivamente la zona de la base | Aprobada |
| D-012 | 2026-07-18 | Administrator | Asignacion explicita de permisos operativos por fase; en 2B recibe `cash.access`, `cash.view_current` y `cash.open` | No heredar todo owner y conservar delegacion practica | Clonar owner; no asignar nada | Employee no recibe `cash.open`; no conceder owner, ganancias, exportaciones, configuracion extra ni funciones inexistentes | Aprobada |

## Prompts de ejecucion por fase

### Prompt Fase 2A

```text
Lee completamente docs/STUDIO_LEMUS_IMPLEMENTATION_PLAN.md e inspecciona el estado actual del repositorio antes de modificar archivos.

Implementa unicamente la Fase 2A: Navegacion por rol y correcciones visuales. Respeta solamente las decisiones del Registro de decisiones que esten marcadas como Aprobadas. Si D-002 u otra decision necesaria continua como Propuesta, detente sin implementar e informa exactamente la aprobacion faltante.

No avances a la Fase 2B y no crees migraciones, modelos, permisos ni logica financiera. El alcance es: quitar Inicio de la experiencia employee; impedir que Inicio sea su destino; resolver la ruta inicial por rol, permisos y destino autorizado; agregar la entrada y pantalla informativa de Caja sin persistencia ni datos simulados; entregar una pantalla clara de Acceso denegado; corregir la navegacion secundaria en resources/js/Layouts/ConfigurationLayout.vue para mostrar Usuarios y Servicios completos; y agregar pruebas de navegacion/autorizacion. Incluye la correccion documentada de persistencia de is_active solo porque afecta acceso, con prueba que verifique el valor guardado. No muestres opciones de fases futuras.

Mantente dentro de los patrones Laravel, Inertia, Vue y Vuetify existentes. La seguridad debe aplicarse en backend, no solo ocultando enlaces. Conserva la interfaz en espanol y valida desktop, tablet y movil sin dos sidebars permanentes ni scroll horizontal.

Ejecuta las pruebas Feature de la fase, php artisan test y npm run build. No declares una prueba correcta si no pudo ejecutarse. Entrega una lista ordenada de pruebas manuales para owner, administrator y employee, incluyendo URLs directas y 1440x900, 1024x768, 768x1024 y 390x844.

Actualiza este documento: cambia 2A a En desarrollo al iniciar y a En pruebas solo cuando codigo, pruebas y build terminen correctamente. No marques 2A Aprobada; esa confirmacion corresponde al usuario. No cambies el estado de 2B.

Entrega diagnostico, cambios, archivos, migraciones (debe ser ninguna), permisos (debe ser ninguno nuevo), pruebas, build, pruebas manuales, riesgos y confirmacion de que no avanzaste. No hagas git add, commit ni push. Detente y espera aprobacion.
```

### Prompt Fase 2B

```text
Lee completamente docs/STUDIO_LEMUS_IMPLEMENTATION_PLAN.md e inspecciona rutas, autenticacion, RBAC, modelos, migraciones, seeders, layouts y pruebas actuales.

Implementa unicamente la Fase 2B: Apertura y caja actual. Verifica primero que 2A figure Aprobada y que D-001, D-002 y D-012 esten Aprobadas. Si falta cualquiera, no implementes y reporta el bloqueo. No avances a 2C.

Entrega la sesion global de caja definida por el plan: maximo una abierta, apertura con fondo inicial y nota opcional, usuario/hora del servidor, consulta de caja actual, estado cerrado y deteccion de caja atrasada. Crea exclusivamente los permisos de Caja que esta fase vuelve funcionales. No implementes ventas, pagos, comprobantes, cierre, movimientos, gastos ni reportes. No agregues CTA o navegacion hacia funciones inexistentes.

La apertura debe usar transaccion y una garantia de base de datos contra dos sesiones abiertas, ademas de validacion de aplicacion. Maneja claramente dos aperturas concurrentes. No uses float. La caja no depende del navegador o dispositivo. Mantiene migraciones nuevas reversibles y no modifiques migraciones existentes. Sigue autorizacion backend granular y asignaciones de rol exactas del plan; employee no recibe cash.open por defecto.

Implementa pantalla y componentes responsive indicados, con estados de carga, vacio, permiso insuficiente, conflicto y caja del dia anterior. No simules totales ni muestres cierre antes de su fase.

Agrega Feature tests de permisos, validacion, unica apertura, persistencia entre sesiones y estado atrasado. Agrega una comprobacion MySQL reproducible para concurrencia, porque SQLite no prueba el mismo bloqueo. Ejecuta php artisan test y npm run build. No ejecutes migrate:fresh sobre datos existentes.

Actualiza 2B a En desarrollo al comenzar y a En pruebas al terminar correctamente; nunca Aprobada. No cambies 2C. Entrega diagnostico, cambios, archivos, migraciones, permisos/asignaciones, pruebas y resultados, build, pasos manuales, riesgos y confirmacion de alcance. No hagas git add, commit ni push. Detente y espera aprobacion del usuario.
```

### Prompt Fase 2C

```text
Lee completamente docs/STUDIO_LEMUS_IMPLEMENTATION_PLAN.md e inspecciona el estado resultante de Caja, Servicios, navegacion, permisos, Resources y estilos responsive.

Implementa unicamente la Fase 2C: Constructor de Nueva venta. Confirma que 2B esta Aprobada y D-003 esta Aprobada. Si no lo estan, detente sin modificar funcionalidad. No avances a 2D y no implementes cobros ni persistencia de ventas.

Crea la pagina dedicada Nueva venta con acceso solo para usuario activo, permisos sales.access/sales.create y caja abierta. El backend debe entregar exclusivamente servicios activos y los datos minimos definidos. El acceso al selector no puede conceder acceso al modulo administrativo Servicios. Implementa buscador, cards/lista, multiples servicios, cantidades enteras, retiro de lineas y subtotal/total preliminares. Los precios no son editables. El carrito puede conservar un borrador no autoritativo en sessionStorage, asociado al usuario y caja, pero debe revalidarse al cargar.

Implementa la distribucion de dos areas en desktop, adaptacion a una o dos columnas en tablet y cards con resumen inferior en movil. No uses tablas densas ni permitas scroll horizontal. Como el cobro pertenece a 2D, no crees un boton que parezca funcional y falle: omite la accion final o presentala claramente como no disponible sin crear una entrada navegable falsa.

Crea solamente los permisos que 2C vuelve funcionales y asigna roles segun la matriz aprobada. No crees tablas sales, sale_items o payments; no generes numeros; no agregues metodos de pago, descuentos, recibos ni datos simulados.

Agrega Feature tests para autorizacion, caja abierta/cerrada, servicios activos, aislamiento del CRUD y props Inertia. Si extraes calculos puros, agrega unit tests. Ejecuta php artisan test y npm run build. Entrega pruebas manuales ordenadas de busqueda, carrito, refresco, cambio de usuario/caja y responsive en cuatro resoluciones.

Actualiza 2C a En desarrollo y despues En pruebas solo si todo pasa. No la marques Aprobada ni alteres 2D. No hagas git add, commit ni push. Entrega archivos, migraciones (ninguna), permisos, pruebas, build, riesgos y confirmacion de que no avanzaste. Detente y espera aprobacion.
```

### Prompt Fase 2D

```text
Lee completamente docs/STUDIO_LEMUS_IMPLEMENTATION_PLAN.md e inspecciona la caja actual, constructor, permisos, modelos, migraciones y pruebas antes de cambiar codigo.

Implementa unicamente la Fase 2D: Cobro y comprobante. Verifica que 2C este Aprobada y que D-004, D-005, D-009, D-010 y D-011 esten Aprobadas. Si alguna sigue como Propuesta, detente e informa el bloqueo. No avances a 2E.

Implementa sales, sale_items y payments conforme al modelo conceptual: decimales, claves/indices, snapshots, checkout_token unico, numero SL basado en ID, estados e inmutabilidad. La venta debe guardarse en una transaccion unica. Dentro de ella valida usuario/permiso, bloquea y verifica caja abierta, recarga servicios activos, detecta cambios de precio/version, recalcula subtotal, descuento y total, valida el pago y crea todos los registros. El navegador nunca es autoridad de montos. Un reintento con el mismo token no puede duplicar la venta.

Habilita efectivo, tarjeta y transferencia con exactamente un pago. No implementes pago mixto, override de precio, conceptos manuales, cortesias, anulacion, historial completo ni cierre. Implementa descuento fijo solo con permiso y motivo segun D-009. No almacenes datos sensibles de tarjeta.

Entrega PaymentDialog seguro contra doble click/cierre accidental, resultado exitoso y comprobante de venta interno en ruta independiente, nueva pestana y CSS termico 80 mm. No lo llames factura fiscal, no inventes SAR/CAI/RTN/impuestos y no agregues PDF. Un fallo de impresion no revierte la venta.

Agrega Feature y unit tests para transaccion, rollback, manipulacion, servicios inactivos/cambiados, pagos, cambio, descuentos, idempotencia, concurrencia, numero, snapshots, permisos, recibo y sesion expirada. Ejecuta php artisan test y npm run build. Prueba manualmente los tres metodos, fallos/reintentos, doble clic y 80 mm.

Actualiza 2D a En desarrollo y luego En pruebas, nunca Aprobada. No alteres 2E. Entrega diagnostico, archivos, migraciones, permisos, pruebas, build, pasos manuales, riesgos y confirmacion de alcance. No hagas git add, commit ni push. Detente y espera aprobacion.
```

### Prompt Fase 2E

```text
Lee completamente docs/STUDIO_LEMUS_IMPLEMENTATION_PLAN.md e inspecciona ventas, Resources, autorizacion, comprobante y patrones de listado existentes.

Implementa unicamente la Fase 2E: Historial, detalle y reimpresion. Confirma que 2D esta Aprobada y D-007 esta Aprobada. Si no, detente sin implementar. No avances a 2F y no agregues anulacion.

Entrega historial paginado y filtrable, detalle inmutable, comprobantes recientes y reimpresion utilizando los snapshots existentes. Implementa en backend los scopes sales.view_own y sales.view_all; sales.view_detail y sales.reprint siempre deben combinarse con el alcance. Employee solo puede ver las ultimas 20 ventas propias de la caja actual conforme a D-007 y debe recibir 403 al escribir un ID ajeno o antiguo no autorizado. Owner y administrator reciben view_all solo segun la matriz aprobada.

Los filtros minimos son numero, fecha, metodo y estado. Usa eager loading controlado y evita N+1. No expongas costos ni referencias sensibles. Editar o eliminar un servicio actual nunca cambia el detalle o recibo historico. No agregues acciones Editar/Eliminar venta, reportes agregados, exportacion, cierres ni opciones futuras.

Mantiene interfaz en espanol: tabla legible en desktop, cards en movil, filtros responsive, skeletons, vacios, 403/404 claros y chips de estado. Reutiliza la ruta de comprobante de 2D; no dupliques su generacion.

Agrega Feature tests para own/all, listado, filtros, binding/URL manual, datos de dos usuarios, snapshots tras cambio/eliminacion del servicio, detalle y reimpresion. Comprueba consultas razonables. Ejecuta php artisan test y npm run build. Entrega pruebas manuales cruzadas con owner, administrator y dos employees, incluyendo responsive y reimpresion.

Actualiza 2E a En desarrollo y luego En pruebas solo cuando todo pase; no la marques Aprobada ni cambies 2F. Entrega diagnostico, cambios, archivos, migraciones justificadas si hubiera un indice, permisos, pruebas, build, pasos manuales y riesgos. No hagas git add, commit ni push. Detente y espera aprobacion del usuario.
```

### Prompt Fase 2F

```text
Lee completamente docs/STUDIO_LEMUS_IMPLEMENTATION_PLAN.md e inspecciona estados de venta, pagos, caja abierta, detalle, permisos y pruebas existentes.

Implementa unicamente la Fase 2F: Anulacion. Verifica que 2E este Aprobada y D-008 este Aprobada. Si falta aprobacion, detente. No avances a 2G ni implementes cierre.

Implementa la transicion unica de completed a canceled sin borrar ni editar venta, items o pagos. Exige sales.cancel, motivo de 10 a 500 caracteres, usuario y hora del servidor. Solo permite anular mientras la caja asociada siga abierta. Owner puede; administrator solo por asignacion explicita aprobada; employee no recibe permiso por defecto y backend debe rechazarlo aunque fabrique la solicitud.

Usa transaccion y bloqueo para evitar doble anulacion y carrera con el futuro cierre. Una anulacion en efectivo reduce el efectivo esperado al excluir la venta. Tarjeta/transferencia no ejecutan reembolso externo: muestra advertencia y exige que el motivo documente la gestion externa. La venta anulada permanece en historial, detalle y comprobante marcado; no cuenta en ingresos y no puede reactivarse. No implementes devoluciones parciales, anulacion de cajas cerradas ni eliminacion fisica.

Actualiza frontend solo donde la funcion existe: accion en detalle, dialogo con motivo, procesamiento, errores y estado anulado. No muestres la accion sin permiso. Los reportes aun no existen, pero deja consultas/calculadores compartidos preparados sin crear pantallas de 2H.

Agrega Feature tests de permisos, motivo, estado, doble solicitud concurrente, caja cerrada, conservacion de snapshots/pagos, efecto sobre agregados de caja e ingresos y no reactivacion. Ejecuta php artisan test y npm run build. Entrega pruebas manuales para efectivo, tarjeta, transferencia, dos usuarios, doble clic y URL fabricada.

Actualiza 2F a En desarrollo y luego En pruebas, nunca Aprobada. No cambies 2G. Entrega archivos, migraciones, permisos, pruebas, build, manuales, riesgos y confirmacion de alcance. No hagas git add, commit ni push. Detente y espera aprobacion.
```

### Prompt Fase 2G

```text
Lee completamente docs/STUDIO_LEMUS_IMPLEMENTATION_PLAN.md e inspecciona caja, ventas, pagos, anulaciones, autorizacion y pruebas de concurrencia actuales.

Implementa unicamente la Fase 2G: Cierre de caja. Confirma que 2F figura Aprobada y D-006 figura Aprobada. Si no, detente sin modificar funcionalidad. No avances a 2H.

Implementa cierre ciego: solicita primero efectivo declarado y nota, sin revelar efectivo esperado; despues de una confirmacion correcta muestra monto inicial, ventas completed por efectivo/tarjeta/transferencia, efectivo esperado, declarado, diferencia, responsable y hora. Antes de 2I no muestres acciones ficticias de entradas, salidas o gastos. La formula de esta fase es fondo inicial mas ventas en efectivo completed; las anuladas no cuentan.

El backend debe recalcular todo con decimales, usar transaccion y lockForUpdate sobre la caja, impedir dos cierres y congelar expected_cash, declared_cash y difference. Exige nota cuando la diferencia no sea cero. Una caja cerrada no se edita, reabre ni elimina. Despues permite una nueva apertura. Implementa historial y detalle con alcance cash.view_history/cash.view_all; employee no ve cierres ajenos y solo puede cerrar si tiene cash.close directo.

No implementes reportes de ingresos, gastos, movimientos, ganancia, exportacion ni cierre automatico. Una caja del dia anterior requiere revision y cierre manual. Si se necesita correccion posterior, conserva nota/evento append-only segun la decision aprobada; nunca cambies silenciosamente snapshots.

Agrega unit tests del calculador y Feature tests de metodos, anuladas, diferencias, nota, permisos, scope, inmutabilidad, nueva apertura y dos cierres concurrentes. Incluye comprobacion MySQL reproducible. Ejecuta php artisan test y npm run build. Entrega un ciclo manual abrir-vender-anular-cerrar, diferencias y dos navegadores, ademas de responsive.

Actualiza 2G a En desarrollo y despues En pruebas; no Aprobada. No alteres 2H. Entrega diagnostico, archivos, migraciones, permisos, pruebas, build, manuales y riesgos. No hagas git add, commit ni push. Detente y espera aprobacion.
```

### Prompt Fase 2H

```text
Lee completamente docs/STUDIO_LEMUS_IMPLEMENTATION_PLAN.md e inspecciona ventas, pagos, anulaciones, cierres, timezone, permisos y consultas existentes.

Implementa unicamente la Fase 2H: Reportes de ingresos. Verifica que 2G esta Aprobada y D-011 esta Aprobada. Si no, detente. No avances a 2I.

Entrega una pantalla simple de Reportes de ingresos con periodos diario, semanal y mensual, conteo de ventas cobradas, total de ingresos, metodos de pago, anulaciones separadas y referencia a cierres/diferencias autorizados. Usa limites de America/Tegucigalpa convertidos correctamente a UTC. Incluye solo ventas completed y evita doble conteo por joins. Cada cifra debe indicar periodo y HNL.

No uses las palabras ganancia bruta o neta para ingresos. No agregues costos, gastos, movimientos, margenes, exportacion, pronosticos ni graficos complejos. No crees datos precalculados o tablas de reportes sin evidencia. Agrega indices solo si una consulta y EXPLAIN justifican la necesidad.

Implementa reports.sales.view y reports.cash.view exclusivamente con las pantallas reales. Owner y administrator reciben la asignacion explicita aprobada; employee no. Protege rutas y datos en backend, no solo en menu. La navegacion Reportes aparece unicamente para usuarios autorizados cuando la pagina este completa.

La interfaz debe tener filtros claros, skeletons, estados vacios, errores y responsive sin tablas densas en movil. Permite drill-down solo hacia ventas/cierres que el usuario pueda consultar. Muestra una ayuda breve: Ingresos = ventas completadas y cobradas; anuladas excluidas.

Agrega Feature tests de permisos, periodos, bordes de medianoche/semana/mes, UTC-Honduras, estados, metodos, dos usuarios/cajas y vacios. Agrega unit tests para limites de periodo/calculos si se extraen. Ejecuta php artisan test y npm run build. Entrega reconciliacion manual contra ventas y cierres conocidos en varias fechas.

Actualiza 2H a En desarrollo y luego En pruebas, nunca Aprobada. No cambies 2I. Entrega diagnostico, archivos, migraciones/indices, permisos, pruebas, build, manuales y riesgos. No hagas git add, commit ni push. Detente y espera aprobacion.
```

### Prompt Fase 2I

```text
Lee completamente docs/STUDIO_LEMUS_IMPLEMENTATION_PLAN.md e inspecciona Servicios, snapshots de venta, caja, cierres, reportes, RBAC y todas las decisiones financieras.

Implementa unicamente la Fase 2I: Gastos, costos y ganancias. Verifica que 2H esta Aprobada y que las decisiones adicionales requeridas por 2I sobre categorias, reversas, datos historicos incompletos y formula neta estan Aprobadas. Si alguna falta, detente y enumera el bloqueo. No implementes modulos posteriores.

Agrega costo estimado restringido a Servicios y snapshot de costo para nuevas sale_items. No inventes costos retroactivos: ventas anteriores quedan con costo desconocido y los reportes deben advertir/excluir segun la decision aprobada. Implementa gastos simples y cash_movements auditados. Un gasto en efectivo vinculado a caja debe impactar el efectivo esperado exactamente una vez. No uses borrado fisico: corrige mediante anulacion o movimiento inverso con actor, tiempo y motivo.

Implementa las formulas visibles: ingresos cobrados; ganancia bruta = ingresos menos costos historicos conocidos; ganancia neta = ingresos menos costos, gastos, devoluciones y otras salidas cubiertas. No muestres una cifra como ganancia completa cuando falten datos; presenta cobertura y advertencias. Employee nunca ve costos, gastos financieros o ganancia. Administrator no recibe reports.profit.view automaticamente salvo decision aprobada.

No agregues inventario, proveedores complejos, compras, nomina, comisiones, fiscalidad, multiempresa, pago mixto, PDF ni exportacion. Mantiene HNL, decimales y timestamps UTC con periodos Honduras.

Agrega Feature/unit tests de confidencialidad, snapshot, historico sin costo, gasto/movimiento unico, cierre, reversas, formulas, anulaciones, periodos y permisos. Ejecuta php artisan test y npm run build. Entrega pruebas manuales de un ciclo completo y reconciliacion numerica.

Actualiza 2I a En desarrollo y luego En pruebas; solo el usuario puede aprobarla. Entrega diagnostico, archivos, migraciones, permisos, pruebas, build, manuales, riesgos y confirmacion de no ampliar alcance. No hagas git add, commit ni push. Detente y espera aprobacion.
```

## Plantilla minima para prompts posteriores

```text
Lee completamente:

docs/STUDIO_LEMUS_IMPLEMENTATION_PLAN.md

Implementa unicamente la fase: [FASE].

Respeta solamente las decisiones marcadas como Aprobadas.

No avances a otra fase.

Ejecuta las pruebas automaticas y el build definidos en la fase.

Actualiza el tablero de progreso a "En pruebas", pero no marques la fase como "Aprobada".

Entrega:

1. Diagnostico.
2. Cambios realizados.
3. Archivos creados y modificados.
4. Migraciones.
5. Permisos.
6. Pruebas ejecutadas.
7. Resultado del build.
8. Pruebas manuales.
9. Riesgos.
10. Confirmacion de que no avanzaste a la siguiente fase.

No hagas git add, commit ni push.

Detente y espera mi aprobacion.
```

## Mantenimiento de este documento

- Antes de iniciar una fase, registrar aprobaciones/rechazos de decisiones con fecha sin borrar la decision original; si cambia, agregar una nueva entrada que la sustituya.
- Al iniciar, cambiar solo esa fase a `En desarrollo`. Al terminar codigo, pruebas y build, cambiarla a `En pruebas`.
- El usuario cambia `Aprobacion` a `Si` y estado a `Aprobada` solo despues de sus pruebas manuales.
- Registrar desviaciones, migraciones reales, permisos finales, riesgos encontrados y resultados de pruebas dentro de la fase correspondiente.
- No reducir controles de concurrencia, auditoria o backend para acelerar una fase.
- Detener la implementacion cuando el documento no cierre una decision que afecte dinero, permisos, inmutabilidad o alcance.

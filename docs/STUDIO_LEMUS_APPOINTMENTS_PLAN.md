# Plan de implementacion de Agenda y Citas

Fecha de creacion: 2026-07-20  
Estado del documento: Fuente oficial del modulo futuro de Agenda y Citas  
Alcance del producto: Un solo Studio Lemus, una ubicacion, HNL, interfaz en espanol y horario `America/Tegucigalpa`

Este documento gobierna exclusivamente Agenda y Citas. Complementa `docs/STUDIO_LEMUS_IMPLEMENTATION_PLAN.md`, pero no modifica los estados, dependencias o aprobaciones de las fases POS 3A-3E. Cada fase requiere aprobacion expresa y se implementa en intervenciones separadas.

## Horario de atención configurable (2026-07-28)

- Estado: En pruebas / No. La migración reversible `2026_07_28_100000_create_business_hours_table.php` crea los siete días estables ISO 1-7, conserva el rango legado configurado como horario inicial y no modifica citas existentes.
- Configuración: owner y administrator administran `/configuration/business-hours` mediante `settings.business_hours.manage`; employee no recibe el permiso ni acceso frontend/backend. La pantalla guarda los siete días en una sola acción, permite cerrar cada día y exige apertura anterior al cierre.
- Agenda: `business_hours` es la fuente autoritativa para disponibilidad, creación y reprogramación. `APPOINTMENTS_SLOT_MINUTES` conserva exclusivamente el intervalo de slots. Un día cerrado no devuelve horarios y una cita nueva solo puede terminar antes o exactamente al cierre.
- Historial: cambiar el horario no altera citas existentes. El detalle advierte cuando una cita actual queda fuera del horario configurado.

## Vencimiento y cobro de citas (2026-07-28)

- La hora final autoritativa es el máximo `scheduled_end` de los `appointment_items`. La cita queda Programada antes del inicio, En atención hasta el final y Pendiente de cobro después de finalizar, sin vencimiento automático.
- Una cita permanece `scheduled` hasta completarse mediante cobro o hasta que una persona la marque manualmente como No llegó o Cancelada.
- Agenda y checkout vuelven a validar el límite en backend. Durante la gracia permite cobro y No llegó, pero no reprogramar ni cancelar; tras vencer, la Agenda procesa y excluye la cita mientras Historial conserva la auditoría.

## Tablero de progreso

| Fase | Nombre | Estado | Dependencias | Aprobacion |
|---|---|---|---|---|
| 4A | Agenda base | Aprobada | APT-D001 a APT-D006, APT-D018, APT-D019 y APT-D020 | Sí |
| 4B | Estados, cancelacion y no-show | En pruebas | 4A aprobada | No |
| 4C | Adelantos y devoluciones | En pruebas | 4B terminada tecnicamente y decisiones financieras aprobadas | No |
| 4D | Atender y cobrar | En pruebas | 4C terminada tecnicamente y decisiones de integracion aprobadas | No |
| 4E | Proyeccion en Ganancias Generales | En pruebas | 4D terminada tecnicamente y decisiones de proyeccion aprobadas | No |
| 4F | Historial y refinamientos | En pruebas | 4E terminada tecnicamente | No |
| 5A | Estabilizacion final, notificaciones y produccion | En pruebas | 4B-4F terminadas tecnicamente | No |

Estados validos: `Pendiente`, `En desarrollo`, `En pruebas` y `Aprobada`. Solo el usuario puede marcar una fase como `Aprobada` despues de las pruebas manuales.

### Resultado tecnico de Fase 5A (2026-07-24)

- Estado: 5A queda `En pruebas / No`; 4B-4F permanecen `En pruebas / No` y 4A conserva `Aprobada / Si`. Produccion queda `Lista para desplegar`, no `Desplegada`, porque no hay dominio ni acceso SSH verificados.
- Integracion: Nueva venta y Atender y cobrar comparten componentes y un unico nucleo financiero backend. Ganancias retira visualmente Otros ingresos y Salidas sin eliminar datos. Las notificaciones database para owner/administrator cubren el ciclo de citas, adelantos, ventas, usuarios y servicios con atomicidad, deduplicacion y lectura segura.
- Migracion: `2026_07_24_120000_create_notifications_table` se aplico en MySQL como batch 10 sin `migrate:fresh`; despues del seeder se conservaron 13 ventas, 9 citas, 2 adelantos, 3 servicios y 4 usuarios.
- Pruebas: `php artisan test` directo ejecuto 254 pruebas, con 20 correctas/191 aserciones y 234 errores por SQLite deshabilitado. Con extensiones cargadas solo para el proceso pasaron 254/254 y 2,239 aserciones. Pint completo, typecheck real, build, caches Laravel, sintaxis shell y `git diff --check` pasaron.
- Produccion: PHP 8.3 queda soportado por el lock, `public/hot` esta ausente y la guia/scripts Hostinger requieren `.env` privado, MySQL, SSL, document root seguro, backup, migraciones `--force`, caches y smoke test.

### Resultado técnico de Fase 4F (2026-07-24)

- Estado y límites: Fase 4F queda `En pruebas / No`; no se autoaprueba. Estados finales del ciclo: 4B, 4C, 4D, 4E y 4F están `En pruebas / No`; 4A conserva `Aprobada / Sí`. No se implementaron vista semanal, recordatorios, WhatsApp, reserva pública, CRM, recurrencia, lista de espera, inventario, facturación fiscal, comisiones laborales ni sucursales. 4F no creó migraciones; la orquestación final aplicó en MySQL seis migraciones aditivas de 4C/4D como batch 8 y una corrección de integridad como batch 9, sin `migrate:fresh` ni eliminación de datos.
- Ruta y contrato: se agregó `GET /appointments/history`, nombre `appointments.history`, antes de `GET /appointments/{appointment}` y dentro de `auth`, `active` y `permission:appointments.access`. `AppointmentsHistoryRequest` exige además `view_all|view_own`, normaliza `client` y `service`, valida los cuatro estados, IDs y fechas con mensajes en español, orden correcto y máximo 366 días inclusivos. Los días Honduras se convierten a UTC mediante `[inicio local, día posterior al final local)`.
- Consulta y filtros: `BuildAppointmentHistoryAction` combina `date_from`, `date_to`, estado, persona, clienta y nombre snapshot del servicio, por lo que un servicio eliminado del catálogo sigue siendo localizable. Ordena por inicio e ID descendentes, pagina de 20 en 20 y conserva la query string. Carga items, personal, adelanto y venta por eager loading, sin N+1. Agenda diaria y calendario mensual continúan consultando exclusivamente `scheduled`.
- Scopes y privacidad: owner y cualquier usuario con `appointments.view_all` consultan todo el alcance y pueden filtrar persona, incluida historia de personas inactivas que aún tienen segmentos. `view_own` exige un `appointment_item.assigned_to` propio; la carga y el filtro de servicio se limitan al mismo segmento propio. Un employee puede enviar su propio ID redundante, pero otro ID recibe el error explícito `No puedes filtrar el historial de otra persona.`. En citas compartidas no recibe servicios, nombres, personal ni subtotales ajenos. El saldo estimado del detalle employee usa su subtotal visible y no el total de la cita.
- DTO y finanzas: `AppointmentHistoryResource` es independiente de los Resources operativos y no expone capacidades mutables. Entrega fecha/hora Honduras, clienta, estado, servicios visibles, personal solo con `view_all`, total estimado visible, resumen de adelanto y venta autorizada. Employee recibe únicamente el estado del adelanto en la lista; montos y disponible quedan para `view_all`. El comprobante solo se enlaza para owner o vendedor propio con `sales.reprint` y `sales.view_own`, exactamente conforme a la autorización existente.
- Interfaz: `Appointments/History` tiene filtros reales, loading, validación, vacío, tabla desktop y cards móviles separadas. Bajo 700 px se oculta la tabla, aparecen cards y los filtros viven en un panel colapsable; no depende de scroll horizontal. Las únicas acciones de un registro son `Ver detalle` y, en completadas autorizadas, `Ver comprobante`. Agenda y `AppLayout` incluyen navegación real a Historial y estados activos distintos.
- Detalle de solo lectura: la página monta una sola instancia de `AppointmentDetailsDialog` y usa el `GET` autorizado existente con todas las props de mutación, asignación y gestión de adelanto en `false`. Conserva snapshots, duración, asignaciones visibles, motivo/resolución autorizada, venta, hora completada y auditoría traducida, sin renderizar JSON ni IDs técnicos. Las completadas muestran su hora aun si no hay venta visible y nunca muestran controles terminales.
- Refinamiento final: se revisaron calendario mensual, Agenda diaria, Nueva cita, disponibilidad, detalle, reprogramación, estados terminales, adelantos, checkout, comprobante, Ganancias e Historial. Solo se corrigieron puntos concretos de bajo riesgo: token CSRF real para disponibilidad, override explícito del diálogo no desplazable, navegación/estado activo de Historial, hora de completado independiente del comprobante, saldo estimado employee prorrateado por segmentos visibles y ocultamiento del disponible financiero bruto. No se rediseñaron módulos ajenos.
- Pruebas historicas: despues del ajuste final de privacidad, `Phase4FAppointmentHistoryTest` y su cobertura estructural pasaron 13 pruebas y 230 aserciones. Los resultados globales vigentes son los de Fase 5A.
- Migración y datos reales: `php artisan optimize:clear`, `php artisan migrate`, `php artisan db:seed`, `php artisan route:list` y `php artisan migrate:status` terminaron correctamente. MySQL conserva 10 ventas, 15 líneas, 6 citas y cero adelantos demo; se retrocargaron exactamente 10 `sale_payments`, uno por cada venta histórica. Seis migraciones figuran en batch 8 y la corrección `restore_standalone_sale_item_uniqueness` en batch 9; esta conserva la unicidad histórica de ventas normales y usa `appointment_item_id` para reservas repetidas. Las 35 rutas colocan Historial antes del detalle dinámico.
- Riesgos: no hay E2E de navegador, comparación visual automatizada, carrera multiproceso MySQL de checkout ni medición de planes SQL/volumen. El buscador depende de la collation real para mayúsculas y acentos. SQLite sigue deshabilitado globalmente. `public/hot` quedó ausente y el build de producción está en `public/build`.
- Pruebas manuales pendientes: validar owner, administrator `view_all` y employee propio/compartido; filtros combinados y límites 11:59 p. m./12:00 a. m. Honduras; servicio eliminado e historial de persona inactiva; 21 o más filas y navegación con filtros; adelanto con y sin montos autorizados; comprobante propio/ajeno; completada/cancelada/no-show sin acciones mutables; detalle largo y auditoría; navegación/active state; responsive 1440x900, 1024x768, 768x1024 y 390x844 sin scroll horizontal ni doble diálogo.

### Resultado tecnico de Fase 4E (2026-07-24)

- Estado y alcance al cierre de 4E: Fase 4E quedó `En pruebas / No`; no se autoaprobó. En ese momento 4F permanecía `Pendiente / No` y todavía no existían Historial, filtros de citas terminales ni refinamientos de 4F. Esta frase es histórica y queda superada por el resultado técnico de 4F anterior. No se agregaron migraciones ni se aplicaron las migraciones pendientes a MySQL.
- Permisos y filtrado de payload: `appointments.view_projection` se crea idempotentemente y se asigna por defecto solo a owner. Administrator y employee no lo reciben. `/earnings` conserva `reports.sales.view`; un administrator con ese permiso obtiene modo `actual`, resultados reales y ninguna propiedad financiera de proyeccion, retenciones, devoluciones o columnas proyectadas por persona. Fabricar `projection` o `both` se rechaza por validacion; employee sigue sin acceso al reporte.
- Contratos: el filtro `mode=actual|projection|both` usa `both` por defecto solo para usuarios autorizados y `actual` para los demas. El payload separa `actual`, `projection`, `other_income` y `outflows`; `summary` y `daily` reales se conservan por compatibilidad. Retenciones y devoluciones son movimientos reales y se entregan en los tres modos a quien tiene `appointments.view_projection`; modo `actual` no entrega proyeccion ni columnas proyectadas, y modo `projection` no entrega ventas reales. La tabla de personas une ejecutores reales y asignados futuros solo cuando ambos contratos estan presentes. La interfaz distingue Resultados reales, Proyeccion, Otros ingresos y Salidas en desktop, tablet y movil, e incluye exactamente la aclaracion aprobada sobre ingreso neto.
- Resultados reales y consultas: los totales globales siguen usando snapshots unicos de cabecera (`sales.total`, `total_services`, `card_fee_amount`, `net_amount`) sin joins que multipliquen ventas. Rendimiento y filtro de persona usan exclusivamente `sale_items.performed_by`, `quantity`, `line_total`, `allocated_card_fee_amount` y `net_line_amount`; `sold_by` permanece como cajero. Metodo de pago usa `EXISTS` sobre `sale_payments`: una venta mixta coincide con cualquiera de sus metodos, muestra completa la venta o las lineas del ejecutor seleccionado y nunca se duplica. Las tres consultas reales originales se mantienen y no consultan caja.
- Proyeccion y formulas: `BuildAppointmentProjectionAction` comparte limites Honduras con resultados reales y carga solo citas `scheduled` cuyo `scheduled_start` cae en el intervalo local convertido a UTC. Global cuenta citas distintas, suma cantidades, toma `expected_total` una vez y muestra el monto original de adelantos pendientes sin sumarlo al bruto. Saldo pendiente es `max(0, esperado - (monto - devuelto - retenido - aplicado))`. Por persona usa `appointment_items.assigned_to`, cantidad y `line_total`; distribuye por separado tanto el monto original recibido como el disponible actual en centavos, por proporcion y posicion estable, dejando el remanente determinista en la ultima linea. Citas compartidas cuentan una vez globalmente, las asignaciones por persona reconcilian exactamente con el total global y cada persona recibe solo sus segmentos. Metodo de pago no filtra proyeccion.
- Otros ingresos y salidas: adelantos retenidos suman `retained_amount` solo para citas canceled/no_show cuyo `resolved_at` cae en el periodo. Devoluciones suman cada fila inmutable de `appointment_deposit_refunds.amount` por `refunded_at`. Ninguno se incorpora a venta real o proyeccion. Con filtro de persona se usa `whereHas` sobre items asignados y cada deposito o devolucion se cuenta una vez aunque la cita sea compartida.
- Pruebas automatizadas: despues de las correcciones de revision, `Phase4EAppointmentProjectionTest` paso 8 pruebas y 210 aserciones; cubre permisos/modos, movimientos reales en modo actual, separacion de payloads, estados excluidos, adelantos, retenciones, devoluciones, ejecutor frente a cajero, asignacion futura, compartidas, reconciliacion exacta del prorrateo, medianoche Honduras, filtros combinados, venta mixta, no filtracion y conversion unica de proyeccion a real. Fase 3 Earnings/Card paso 28/28 con 332 aserciones; el filtro completo Appointment paso 137/137 con 1,247 aserciones. La ordenacion monetaria de personas usa centavos enteros, sin conversion a `float`. Pint dirigido y `npm run build` pasaron; build transformo 1,199 modulos y conserva la advertencia no bloqueante por chunk mayor de 500 kB. La comprobacion adicional `vue-tsc --noEmit` queda bloqueada por la incompatibilidad instalada de `vue-tsc` con TypeScript 7 (`./lib/tsc` no exportado), no por un diagnostico de la pagina.
- Riesgos y comprobaciones manuales: falta validar volumen y planes SQL reales en MySQL, especialmente `EXISTS`, rangos UTC y scopes `whereHas`; no hay E2E de navegador ni comparacion visual automatizada. Pendiente probar owner y administrator con/sin concesion, intentos de modo fabricado, ventas mixtas, cita compartida con centavos impares, adelanto parcial/retenido/devuelto, conversion y refresco inmediato; revisar Hoy/Semana/Mes/Personalizado y responsive 1440x900, 1024x768, 768x1024 y 390x844. Tambien debe confirmarse que el metodo se entiende como filtro exclusivo de pagos reales y que ningun importe proyectado se interpreta como ingreso ya obtenido.

### Resultado tecnico de Fase 4D (2026-07-24)

- Estado: Fase 4D queda `En pruebas / No`; no se autoaprueba. Fases 4E y 4F permanecen `Pendiente / No`; no se implementaron proyeccion de citas futuras, Historial ni refinamientos posteriores.
- Migraciones y datos: se agregaron cuatro migraciones aditivas y reversibles, todavia no aplicadas a MySQL. `sales.appointment_id` es FK nullable y unica; ventas historicas conservan `null`. `sale_items` reemplaza la unicidad por servicio con posicion estable unica por venta, FK opcional al item reservado y `performed_by` nullable como exige el esquema aprobado; el backfill conserva cada linea, toma `performed_by` de `sales.sold_by` cuando es posible, deja comision `0.00` y copia `line_total` a `net_line_amount`. `sale_payments` retrocarga exactamente un pago final por venta historica desde snapshots persistidos, sin recalcular comisiones. `appointment_deposit_refunds.purpose` distingue operaciones terminales y de excedente para idempotencia vinculada.
- Modelos e inmutabilidad: se agrego `SalePayment` y relaciones cita-venta, item reservado-linea, persona ejecutora y adelanto-pago. `Sale` no admite borrado fisico; `SaleItem` no admite edicion ni borrado despues de crearse; `SalePayment`, adelantos y devoluciones son inmutables. La asignacion inicial de `sale_number` y los fixtures de creacion permanecen validos. Ventas standalone siguen consolidando servicios repetidos y escriben ejecutor para toda linea nueva sin cambiar ruta o request publico.
- Permisos y scope: `appointments.convert_to_sale` se crea idempotentemente para owner, administrator y employee. Entrada y confirmacion exigen usuario activo, acceso a citas, permiso de conversion y `sales.access/create`. Owner/administrator operan con `appointments.view_all`; employee puede convertir una cita propia o compartida si participa en al menos un item. Sin `appointments.assign`, conserva ejecutores reservados y todo adicional se autoasigna al employee; con ese permiso solo admite usuarios activos con `appointments.perform`.
- Rutas y contratos: `GET /sales/new?appointment={id}` precarga clienta, snapshots, ejecutores, duracion/total, monto original y disponible del adelanto, saldo y capacidad de resolver, sin exponer comision ni mutar estado. `POST /appointments/{appointment}/deposit/refund-excess` (`appointments.deposit.refund-excess`) exige `appointments.resolve_deposit`, scope y token UUID; crea una devolucion parcial append-only sin cancelar/no-show. `POST /appointments/{appointment}/checkout` permanece dedicado y `POST /sales` sigue standalone. Los items reservados usan snapshots aunque el catalogo cambie o se elimine; retirar una reserva exige confirmacion explicita.
- Atomicidad e idempotencia: una transaccion bloquea cita, items, adelanto, servicios adicionales y ejecutores; revalida scope/estado/venta unica; crea venta, lineas y pagos; aplica el adelanto; completa la cita; registra evento `completed`; y confirma todo junto. El token liga cita, carrito, retiros, ejecutores y metodo. Repetir el mismo token/payload devuelve la venta; otro token recibe conflicto claro. La FK unica de cita y el token unico cubren la carrera en base de datos.
- Formulas: el disponible autoritativo es `amount - refunded_amount - retained_amount - applied_amount`. La devolución previa exige `0 < refund < disponible`, acumula `refunded_amount`, conserva estado `pending` y no altera monto/tasa/comision/neto originales. Cancelacion/no-show resuelven solo el disponible y acumulan devoluciones previas. Checkout aplica y paga solo el disponible; si servicios quedan por debajo, bloquea hasta devolver la diferencia exacta. La comision original del adelanto se conserva y el 4% nuevo aplica solo al saldo final; la asignacion proporcional en centavos mantiene remanente exacto en la ultima linea.
- Interfaz y recibo: checkout muestra monto disponible y excedente exacto. Usuarios con `resolve_deposit` obtienen el formulario inline real `Devolver excedente`, con loading, errores y token nuevo; los demas reciben instruccion de acudir a responsable. El POST vuelve a la misma URL y refresca contexto sin `VDialog` adicional. Cancelacion/no-show muestran saldo disponible. Recibo y detalle conservan la informacion segura anterior, sin comision POS, netos ni costos internos.
- Pruebas: la ejecucion directa previa de `Phase3ASaleTest` registro el bloqueo ambiental esperado: 26 pruebas, 26 errores `could not find driver`, 0 aserciones. Con extensiones SQLite temporales, 4D/backfill/correccion pasaron 20 pruebas y 228 aserciones; Phase 3 paso 55/481; Phase 3 + 4B/4C/4D paso 110/1,027; todas las pruebas `Appointment` pasaron 129/1,037. Cubren nullabilidad, backfill, permisos/scope, token por monto/deposito, snapshot de fee, disponible, checkout posterior, acumulacion terminal, rollback e inmutabilidad financiera. Pint dirigido y `npm run build` pasaron; build transformo 1,199 modulos y conserva la advertencia no bloqueante por chunk mayor de 500 kB. `git diff --check` paso.
- Riesgos y pruebas manuales: no se aplicaron migraciones a MySQL ni se ejecuto carrera multiproceso MySQL real; deben validarse lock/unicidad y el `purpose` enum en la orquestacion final. No hay E2E de navegador ni comparacion visual automatizada. Pendiente probar excedente exacto y errores como owner, administrator con/sin concesion y employee; doble clic/token distinto; devolución seguida de checkout, cancelacion y no-show; efectivo/tarjeta y fee original; rollback; responsive 1440x900, 1024x768, 768x1024 y 390x844. Fases 4E/4F siguen fuera de alcance.

### Resultado tecnico de Fase 4C (2026-07-24)

- Estado: Fase 4C queda `En pruebas / No`; no se autoaprueba. Fase 4D permanece `Pendiente / No` y no se implementaron ventas, checkout, aplicacion del adelanto, proyeccion o Historial.
- Migraciones y modelos: se agregaron las migraciones aditivas y reversibles `appointment_deposits` y `appointment_deposit_refunds`, con FKs restrictivas, un adelanto unico por cita, token UUID unico por devolucion, indices operativos y montos `decimal`. `AppointmentDeposit` conserva el snapshot original y prohíbe borrado; `AppointmentDepositRefund` es append-only, sin edicion ni borrado. Se agregaron relaciones con cita, usuarios, devoluciones y auditoria.
- Permisos: `appointments.manage_deposit` se asigna por defecto a owner y administrator; `appointments.resolve_deposit` solo a owner. Employee no recibe ninguno. Los seeders son idempotentes. Registro y resolucion se protegen en middleware, Form Request y acciones; administrator necesita concesion separada de `resolve_deposit` para cancelar o marcar no-show una cita con adelanto pendiente.
- Rutas y contratos: existe solo `POST /appointments/{appointment}/deposit` (`appointments.deposit`) para registro posterior. `POST /appointments` acepta adelanto opcional y lo confirma dentro de la misma transaccion de la cita. Cancelacion y no-show aceptan `full_refund`, `full_retention` o `partial_refund`; las devoluciones usan `operation_token` UUID idempotente. No se agrego endpoint separado de refund ni rutas de venta, checkout, proyeccion o Historial.
- Formulas: todo calculo autoritativo usa centavos enteros. Efectivo guarda tasa/comision `0.00` y neto igual al monto. Tarjeta guarda tasa `4.00`, comision redondeada al centavo y neto igual a monto menos comision. Devolucion completa deja devuelto=monto y retenido=0; retencion deja devuelto=0 y retenido=monto; parcial exige `0 < devuelto < monto` y retenido=monto-devuelto. El snapshot POS no cambia al resolver y `applied_amount` permanece `0.00` en 4C.
- Interfaz y datos: Nueva cita permite Sin adelanto o Registrar adelanto. El unico `AppointmentDetailsDialog` incorpora registro posterior y resolucion terminal, sin segundo `VDialog`. El detalle muestra recibido, metodo, estado, devuelto, retenido y saldo estimado; comision/neto y notas internas solo llegan a usuarios con permiso financiero. Los eventos `deposit_recorded` y `deposit_resolved` conservan actor, hora, notas y cambios con etiquetas en espanol.
- Pruebas: la ejecucion directa de `Phase4CAppointmentDepositTest` registro el fallo ambiental esperado de SQLite: 15 pruebas, 15 errores `could not find driver`, 0 aserciones. Con extensiones temporales, Fase 4C paso 15 pruebas y 125 aserciones. La ejecucion conjunta de 4C, 4B, migracion y estructura de dialogo paso 45 pruebas y 415 aserciones; el filtro completo `Appointment` paso 109 pruebas y 807 aserciones. Pint se ejecuto sobre todo PHP cambiado. `npm run build` paso con 1,199 modulos.
- Riesgos: no hay E2E de navegador, comparacion visual automatizada ni carrera multiproceso MySQL. La devolucion registra la operacion financiera pero no procesa reembolsos externos ni almacena datos de tarjeta. SQLite sigue deshabilitado globalmente y el bundle conserva la advertencia no bloqueante por superar 500 kB.
- Pruebas manuales pendientes: registrar efectivo/tarjeta al crear y desde detalle; comprobar limite contra total, doble clic y segundo adelanto; cancelar/no-show con devolucion completa, parcial y retencion; repetir token; verificar rollback visible; probar owner, administrator con/sin permiso adicional y employee; revisar ocultamiento de comision/notas y responsive en 1440x900, 1024x768, 768x1024 y 390x844.

### Progreso interno de Fase 4A

| Prompt | Nombre | Estado | Aprobacion |
|---|---|---|---|
| 1 de 2 | Agenda diaria y creacion de citas | Aprobado | Si |
| 2 de 2 | Detalle y reprogramacion | Aprobado | Si |

El Prompt 1 no autoriza detalle editable, reprogramacion, cancelacion, no-show, adelantos, devoluciones, integracion con ventas o proyeccion. La Fase 4A completa no puede aprobarse hasta terminar y probar ambos prompts.

**Aprobacion manual del Prompt 1 (2026-07-20):** el usuario confirmo que las pruebas manuales de Agenda diaria y creacion de citas fueron satisfactorias. La aprobacion se limita al Prompt 1 y no aprueba la Fase 4A completa.

**Aprobación manual de Fase 4A (2026-07-21):** el usuario confirmó la validación manual completa de Agenda base, detalle, reprogramación, calendario, responsive y hotfix final. Fase 4A queda `Aprobada / Sí` y habilita exclusivamente el inicio de Fase 4B.

### Progreso interno de Fase 4B

| Prompt | Nombre | Estado | Aprobacion |
|---|---|---|---|
| 1 de 2 | Cancelación y estado No llegó, sin adelantos | Aprobado | Sí |
| 2 de 2 | Reestructuración de usabilidad, acciones rápidas y pulido de estados | En pruebas | No |

### Hotfixes de Fase 4B

| Ajuste | Nombre | Estado | Aprobacion |
|---|---|---|---|
| Hotfix final de Fase 4B | Ocultar estados terminales de la Agenda operativa | En pruebas | No |

#### Resultado del Hotfix final de Fase 4B (2026-07-21)

- Estado: hotfix `En pruebas / No`. Fase 4B permanece `En pruebas / No` y no se aprueba automáticamente. Fase 4C continúa `Pendiente / No`.
- Regla operativa: Agenda diaria y calendario mensual representan exclusivamente trabajo pendiente. Solo `scheduled` aparece en listas, línea horaria, conteos, servicios y previews. `canceled`, `no_show` y el futuro `completed` quedan fuera de la operación sin eliminarse.
- Consulta diaria anterior: filtraba únicamente `scheduled_start >= inicio local` y `scheduled_start < fin local`, seguido por scope de items y empleado. Por eso incluía cabeceras terminales del mismo día.
- Consulta diaria corregida: agrega `where('status', Appointment::STATUS_SCHEDULED)` en backend antes del rango y los scopes. Las props Inertia nunca contienen citas terminales; Vue no realiza el filtro de seguridad ni conserva cards fantasma.
- Calendario mensual: `BuildAppointmentCalendarAction` ya consultaba `appointment_items` con `whereHas('appointment', status=scheduled)`. No requirió cambio funcional; la cobertura nueva protege que canceled, no-show y completed no incrementen `appointments_count`, `services_count`, previews ni actividad del día para view_all o employee. Al cancelar la última cita activa, la acción devuelve el día vacío.
- Disponibilidad: `BuildAppointmentAvailabilityAction`, creación y reprogramación ya detectaban conflictos exclusivamente mediante items cuya cita es `scheduled`. No se modificó la lógica de intervalos. Las pruebas acumuladas confirman que cancelar o marcar no-show libera inmediatamente el horario; completed usa el mismo filtro excluyente.
- Actualización inmediata: cancelación y no-show ahora responden `back(303)` a la URL operativa completa. Inertia recibe nuevas props filtradas, cierra el único diálogo y retira card y segmentos sin recarga manual. Si no queda otra cita, el `appointments.length === 0` existente muestra el estado vacío. Se conservan `view`, fecha, mes, `employee_id`, scroll y snackbar.
- Mensajes: permanecen exactamente `La cita fue cancelada correctamente.` y `La cita fue marcada como No llegó.`
- Detalle directo: `GET /appointments/{appointment}` conserva autorización independiente de la lista y continúa cargando terminales con estado, motivo, actor, fecha/hora, items y eventos. No se agregó listado histórico, filtro de estados ni navegación hacia terminales.
- Persistencia: no se eliminaron registros ni se agregó soft delete. Las pruebas confirman conservación de appointments, appointment_items, eventos append-only, razones y actores después de retirar las cards operativas.
- Archivos creados: ninguno.
- Archivos modificados: `AppointmentController.php`, `AppointmentCalendarTest.php`, `Phase4BAppointmentStatusTest.php` y este plan. `BuildAppointmentCalendarAction.php` y `BuildAppointmentAvailabilityAction.php` fueron inspeccionados y cubiertos, pero ya cumplían la regla y no se alteraron.
- Operación: `php artisan optimize:clear` correcto; `php artisan migrate` respondió `Nothing to migrate`; `php artisan db:seed` ejecutó PermissionSeeder y RoleSeeder correctamente. No hubo migraciones, permisos, dependencias ni datos demo.
- Pruebas directas: `php artisan test --filter=Appointment` detectó 91 pruebas, 8 correctas y 83 errores; `php artisan test` detectó 179 pruebas, 9 correctas y 170 errores. Todos los errores fueron `could not find driver` por SQLite global deshabilitado; no se declaran ejecuciones correctas.
- Pruebas con SQLite por proceso: Agenda pasó 91 pruebas y 666 aserciones; la suite completa pasó 179 pruebas y 1,293 aserciones. No se creó INI, base o archivo temporal persistente.
- Build: `npm run build` correcto con 1,199 módulos. Assets principales CSS 837.02 kB y JS 884.05 kB; persiste la advertencia no bloqueante por chunks mayores de 500 kB.
- Pruebas manuales pendientes: abrir día con varias citas y cancelar una; confirmar snackbar, cierre y retiro inmediato; repetir no-show; cancelar la última y verificar estado vacío; volver al mes y confirmar día sin badge/previews; repetir con filtro de empleado; verificar otra persona y cita compartida; abrir URLs directas terminales autorizadas y comprobar motivo/auditoría; revisar 1440x900, 1024x768, 768x1024 y 390x844 sin huecos ni segmentos residuales.
- Riesgos: no existe todavía una pantalla para descubrir citas terminales y su consulta directa requiere conocer el ID; esto es deliberado hasta una fase futura aprobada de Historial. No hay E2E de navegador ni comparación visual automatizada. SQLite continúa deshabilitado globalmente y el bundle supera 500 kB.
- Límite: no se implementaron Historial, filtros por estado, Mostrar canceladas, restauración, reactivación, eliminación física, adelantos, devoluciones, ventas desde citas ni proyección. No se avanzó a Fase 4C.

#### Resultado de Fase 4B — Prompt 2 de 2 (2026-07-21)

- Estado: Prompt 1 queda `Aprobado / Sí` por la instrucción expresa condicionada a que todas las verificaciones pasaran. Prompt 2 y Fase 4B global quedan `En pruebas / No`; solo el usuario puede aprobar 4B después de la validación manual. Fase 4C permanece `Pendiente / No`.
- Diagnóstico: la Agenda diaria concentraba todas las acciones dentro del detalle. Cada operación requería abrir detalle primero; reprogramar, cancelar y no-show estaban duplicados en un footer ancho. El `VDialog` con `scrollable`, card flexible, footer permanente y `max-height` producía la zona vacía de la captura. Las cards respondían quién/cuándo/qué, pero no qué podía hacer la persona.
- Divulgación progresiva: la card responde hora, clienta, servicios, personal visible, estado y acciones permitidas. El detalle queda dedicado a información completa, segmentos, duración, total, notas, estado terminal e historial. Cada formulario solicita únicamente los datos de su operación.
- Desktop: cada cita `scheduled` muestra una barra compacta en orden `Ver detalle`, `Reprogramar`, `Cancelar` y `No llegó`. Se usan botones pequeños de texto e iconos; solo se renderizan acciones reales autorizadas. No-show aparece únicamente cuando el indicador backend confirma que comenzó la cita.
- Móvil: cada card muestra `Ver detalle` como acción principal. `Más acciones` se renderiza solo cuando contiene al menos una operación autorizada y agrupa Reprogramar, Cancelar y No llegó; no existen cuatro botones horizontales ni entradas vacías.
- Apertura directa: Reprogramar, Cancelar y No llegó abren el mismo `AppointmentDetailsDialog` directamente en su modo mediante `initialMode`. No se abre detalle primero y no se agregaron diálogos superpuestos. Una validación al recibir el detalle degrada a modo informativo si el estado, scope, permiso o momento ya cambió.
- Detalle: se retiraron Reprogramar, Cancelar cita y Marcar No llegó del footer. Solo conserva `Editar información` cuando la cita sigue programada y el scope permite mutarla. Cerrar permanece visible en el header. Citas compartidas son completamente informativas para employee.
- Diálogo: continúa existiendo un único `VDialog`. Desktop usa `max-width=780`, altura natural y máximo `85vh`; header y footer quedan fuera del único contenido desplazable. El footer no se monta durante carga, error o detalle sin edición. Móvil usa `100dvh`, contenido flexible y un único scroll interno. Se eliminó `scrollable` del shell que forzaba el espacio artificial.
- Carga y error: el detalle usa skeleton compacto de aproximadamente 150 px, sin reservar paneles futuros. Los errores usan alerta compacta con Reintentar. El cierre `after-leave` limpia modo, cita seleccionada, errores, cuatro formularios, disponibilidad y temporizador antes de reabrir.
- Estados terminales: canceladas y no-show conservan chip, card atenuada con borde discontinuo, motivo resumido a dos líneas y solo `Ver detalle`. Backend entrega capacidades mutables falsas para estados terminales. Estas citas continúan sin bloquear disponibilidad.
- Seguridad: la card combina permisos efectivos con indicadores backend `can_reschedule`, `can_change_status` y `can_mark_no_show_now`. Employee no recibe acciones mutables para citas compartidas y backend ahora rechaza también editar su información; owner y administrator con `view_all` conservan su alcance. Rutas, middleware, bloqueo transaccional, no-show por hora y terminalidad no se debilitaron.
- Mejoras de bajo riesgo: se redujo padding de cards, se compactaron separaciones, notas y motivos se limitan visualmente a dos líneas, y el menú móvil solo existe con contenido. Calendario, navegación de fechas, disponibilidad, Nueva cita, edición y reprogramación conservaron contratos y reglas existentes.
- Archivos creados: ninguno.
- Archivos modificados: `ApplyAppointmentChangesAction.php`, `AppointmentResource.php`, `AppointmentDetailsResource.php`, `appointments.ts`, `Appointments/Index.vue`, `AppointmentDetailsDialog.vue`, `AppointmentDialogStructureTest.php`, `Phase4BAppointmentStatusTest.php` y este plan.
- Migración y datos: `php artisan optimize:clear` correcto; `php artisan migrate` respondió `Nothing to migrate`; `php artisan db:seed` terminó correctamente con PermissionSeeder y RoleSeeder. No se creó migración, permiso, dependencia o dato demo.
- Pruebas directas: `php artisan test --filter=Appointment` detectó 89 pruebas, 8 correctas y 81 errores; `php artisan test` detectó 177 pruebas, 9 correctas y 168 errores. Todos los errores fueron `could not find driver` por SQLite global deshabilitado; estas ejecuciones no se declaran correctas.
- Pruebas con SQLite por proceso: Agenda pasó 89 pruebas y 593 aserciones; la suite completa pasó 177 pruebas y 1,220 aserciones. Las pruebas específicas de estructura pasaron 8/79 y las de estados/UX backend 17/130. No se creó configuración ni archivo temporal.
- Build: `npm run build` correcto con 1,199 módulos. Los assets finales principales son CSS 837.02 kB y JS 884.05 kB; persiste la advertencia no bloqueante por chunks mayores de 500 kB.
- Formato: Pint dirigido a todos los PHP intervenidos pasó. La comprobación global detectó estilo preexistente pendiente en 14 archivos no intervenidos; no se reescribieron archivos ajenos al alcance.
- Pruebas manuales pendientes: revisar card programada como owner, administrator y employee; retirar permisos y confirmar acciones ausentes; abrir cada acción directamente; validar compartida employee solo informativa; confirmar no-show ausente antes de hora y visible al recargar desde la hora; cancelar/no-show y comprobar actualización, motivo, atenuación y ausencia de acciones; cerrar/reabrir cada modo; provocar loading/error; revisar contenido corto/largo y 1440x900, 1024x768, 768x1024 y 390x844 sin espacio blanco artificial, doble scroll o desbordamiento.
- Mejoras recomendadas no implementadas: pruebas E2E reales de navegador, actualización automática de la disponibilidad de No llegó cuando una pantalla permanece abierta atravesando la hora inicial, división futura del bundle y medición visual automatizada. No se agregó ninguna porque excede el cambio de bajo riesgo o requiere infraestructura adicional.
- Riesgos: no hay E2E ni comparación visual automatizada; una Agenda abierta antes de la cita requiere recargar/navegar para recibir el nuevo indicador backend de no-show; el bundle supera 500 kB; SQLite sigue deshabilitado globalmente; dos pestañas pueden mostrar capacidades obsoletas hasta que backend rechace la operación.
- Límite: no se implementaron adelantos, depósitos, devoluciones, retenciones, proyección financiera, integración con ventas ni `Atender y cobrar`. No se crearon botones falsos o funciones de Fase 4C/4D.

#### Resultado de Fase 4B — Prompt 1 de 2 (2026-07-21)

- Estado: Fase 4A queda `Aprobada / Sí` por validación manual expresa. Fase 4B Prompt 1 queda `En pruebas / No`; Fase 4B global permanece `En desarrollo / No` porque Prompt 2 continúa `Pendiente / No`. Fase 4C permanece `Pendiente / No`.
- Decisiones: APT-D028 a APT-D032 registran transiciones terminales únicas, motivos obligatorios de 5 a 500 caracteres, no-show desde la hora inicial en Honduras, scope completamente propio para employee y los dos permisos exactos para los tres roles.
- Datos: la tabla ya tenía `canceled_at`, `canceled_by`, `cancellation_reason`, `no_show_at` y `no_show_by`. Se creó y aplicó la migración reversible `2026_07_21_100000_add_no_show_reason_to_appointments_table.php`, que agrega únicamente `no_show_reason nullable`. No se modificaron migraciones históricas, no se eliminaron citas y no se ejecutó `migrate:fresh`.
- Permisos: se crearon exclusivamente `appointments.cancel` y `appointments.mark_no_show`. Owner, administrator y employee reciben ambos mediante seeders idempotentes. Middleware exige el permiso específico y las acciones vuelven a validar usuario activo, acceso y alcance después del bloqueo.
- Dominio: `CancelAppointmentAction` y `MarkAppointmentNoShowAction` delegan en una transición compartida transaccional. La cita y sus items se bloquean con `lockForUpdate`; solo `scheduled` puede cambiar; employee requiere todos los items propios; usuarios con `view_all` actúan sobre cualquier cita visible. Estado, actor, hora UTC, motivo y evento append-only confirman juntos; una segunda transición recibe error claro y un fallo de auditoría revierte todo.
- Cancelación: guarda `status=canceled`, `canceled_at`, `canceled_by` y `cancellation_reason`. Ruta `POST /appointments/{appointment}/cancel`, nombre `appointments.cancel`. La interfaz muestra clienta, fecha/hora, servicios, motivo y la advertencia `La cita dejará de ocupar estos horarios.` dentro del único diálogo.
- No llegó: guarda `status=no_show`, `no_show_at`, `no_show_by` y `no_show_reason`. Ruta `POST /appointments/{appointment}/no-show`, nombre `appointments.no-show`. Backend compara el instante inicial usando `America/Tegucigalpa`; antes de la hora rechaza y la interfaz deshabilita la acción con `Podrás marcar No llegó cuando haya comenzado la hora de la cita.`
- Interfaz: `AppointmentDetailsDialog` sigue siendo un solo `VDialog` y agrega únicamente modos internos `cancel` y `no_show`. Procesamiento bloquea cierre accidental; éxito refresca Agenda y detalle. Citas terminales conservan detalle e historial, ocultan todas las acciones mutables y muestran motivo, actor y fecha. Agenda diaria las mantiene con card atenuada; chips usan Programada/primary, Completada/success, Cancelada/error y No llegó/warning.
- Auditoría: se agregaron eventos `canceled` y `no_show`, rotulados `Cita cancelada` y `La clienta no llegó`. El detalle presenta cambio de estado, motivo, actor y hora Honduras sin JSON ni IDs técnicos; los eventos continúan inmutables.
- Disponibilidad: creación, reprogramación, calendario mensual y disponibilidad ya consideran conflicto solo contra `scheduled`; las pruebas confirman que `canceled` y `no_show` liberan el horario. Una cita terminal tampoco puede consultar disponibilidad de reprogramación.
- Archivos nuevos: migración `add_no_show_reason`, requests `CancelAppointmentRequest` y `MarkAppointmentNoShowRequest`, acciones `CancelAppointmentAction`, `MarkAppointmentNoShowAction`, `TransitionAppointmentStatusAction` y prueba `Phase4BAppointmentStatusTest`.
- Archivos modificados: `AppointmentController.php`, `Appointment.php`, `AppointmentEvent.php`, `AppointmentResource.php`, `AppointmentDetailsResource.php`, `Permissions.php`, `RoleSeeder.php`, `routes/web.php`, `AppointmentDetailsDialog.vue`, `Appointments/Index.vue`, `appointments.ts`, pruebas acumuladas de migración/rutas/diálogo y este plan.
- Verificación operativa: `php artisan optimize:clear`, `php artisan migrate` y `php artisan db:seed` correctos. La migración fue aplicada en MySQL y los seeders terminaron correctamente. Pint correcto.
- Pruebas directas: `php artisan test --filter=Appointment` detectó 84 pruebas, 5 correctas y 79 errores; `php artisan test` detectó 172 pruebas, 6 correctas y 166 errores. Todos los errores fueron `could not find driver` por SQLite global deshabilitado; estas ejecuciones no se declaran correctas.
- Pruebas con SQLite por proceso: suite dirigida de Agenda 84 pruebas y 518 aserciones correctas; suite completa 172 pruebas y 1,145 aserciones correctas. Las 15 pruebas nuevas de 4B aportan 84 aserciones. No se crearon archivos temporales.
- Build: `npm run build` correcto con 1,199 módulos. Persiste la advertencia no bloqueante por chunks mayores de 500 kB.
- Pruebas manuales pendientes: cancelar como owner, administrator y employee propio; intentar compartida y permiso retirado; probar motivo vacío/corto/largo y doble clic; marcar no-show antes, exactamente a la hora y después; revisar cita compartida; confirmar horario liberado creando otra cita; revisar chip, atenuación, motivo, actor, fecha e historial; cerrar/reabrir cada modo; validar 1440x900, 1024x768, 768x1024 y 390x844.
- Riesgos: no hay E2E de navegador ni carrera multiproceso MySQL real entre dos transiciones, aunque `lockForUpdate` y la segunda validación serializan la cita; SQLite sigue deshabilitado globalmente; dos pestañas pueden mostrar acciones obsoletas hasta enviar y recibir el rechazo terminal; el bundle supera 500 kB.
- Límite: no existen rutas, tablas, campos, permisos ni interfaz de adelantos, depósitos, devoluciones, retenciones, reactivación, eliminación, ventas desde citas o proyección. No se avanzó al Prompt 2.

### Ajustes posteriores a validacion de Fase 4A

| Ajuste | Nombre | Estado | Aprobacion |
|---|---|---|---|
| 1 de 3 | Correccion de modales, restriccion de reprogramacion e historial entendible | Aprobado | Sí |
| 2 de 3 | Asignacion de personal y duracion personalizada por servicio | Aprobado | Sí |
| 3 de 3 — Prompt 1 de 2 | Calendario mensual y selección del día | Aprobado | Sí |
| 3 de 3 — Prompt 2 de 2 | Simplificación visual, transición y responsive | Aprobado | Sí |
| 3 de 3 | Calendario mensual completo | Aprobado | Sí |
| Hotfix final de Fase 4A | Disponibilidad al reprogramar y cierre del detalle | Aprobado | Sí |

#### Resultado del Hotfix final de Fase 4A (2026-07-20)

- Estado: hotfix `En pruebas / No`. Fase 4A permanece `En pruebas / No` hasta validación manual. Fase 4B continúa `Pendiente / No`.
- Causa de disponibilidad: `Index.vue` abría el diálogo y solicitaba el detalle después. El watcher observaba únicamente `modelValue`, se ejecutaba con `appointment = null` y no volvía a inicializar al llegar el detalle; Reprogramar enviaba `date: ""`. Además enviaba un payload de creación reconstruido desde `visible_items`, sujeto a `service_id` vigente y valores del navegador. `bootstrap/app.php` forzaba HTML en excepciones de rutas web aunque Fetch solicitara JSON, impidiendo distinguir correctamente 422 y 403. No hubo una entrada específica del incidente en `laravel.log`: era una validación 422, no una excepción 500; las entradas revisadas eran anteriores o de pruebas inducidas.
- Contrato anterior: `{ appointment_id, date, items: [{ service_id, assigned_to, quantity, duration_minutes }] }`. La fecha podía ir vacía y cantidad/duración se reenviaban desde el navegador como si fuera creación.
- Contrato corregido: `{ appointment_id, date, assignments: [{ appointment_item_id, assigned_to }] }`. `assignments` solo se envía completo para usuarios con `appointments.assign`; employee propio envía un arreglo vacío. El backend carga los `appointment_items` de la cita por posición, toma de base cantidad y duración reservadas, conserva la persona actual salvo cambio autorizado, valida que los IDs pertenezcan a la cita y que las personas estén activas con `appointments.perform`, y excluye `appointment_id` de conflictos. Un employee no recibe IDs ajenos y una cita compartida conserva el mensaje autorizado existente.
- Precarga y consulta: el watcher observa conjuntamente apertura y llegada del detalle, precarga fecha, hora y asignaciones actuales, y Reprogramar consulta automáticamente con debounce. La hora actual permanece disponible si no existe conflicto ajeno; otra cita ocupada se excluye. El selector muestra loading y solo acepta horas devueltas por backend.
- Errores: sin fecha muestra `Selecciona una fecha.`; una respuesta sin slots muestra `No hay horarios disponibles.`; 422 muestra el primer mensaje autorizado de fecha/cita/asignación; 403 muestra falta de permiso; 404 informa que la cita ya no está disponible; fallos inesperados conservan un mensaje general. Solicitudes JSON web ahora respetan `expectsJson()`; Laravel conserva su registro estándar para excepciones inesperadas.
- Causa del residuo visual: el cierre anterior solo emitía `update:modelValue=false`; modo, formularios, errores, loading y cita seleccionada permanecían vivos dentro del contenido teletransportado del diálogo. No existía una limpieza posterior a la transición ni un render condicional del card.
- Cierre: continúa existiendo un solo `VDialog`. `closeDialog()` centraliza X, Cerrar detalle y `update:modelValue` de Escape/clic externo. El diálogo es persistente en edit/reschedule y durante guardado o consulta. El card solo existe con `modelValue`; `after-leave` restablece modo detail, timer, loading, horarios, mensajes y errores, y el padre limpia ID, cita y error seleccionados. Reabrir siempre comienza en detail. No se agregó CSS global ni recarga completa.
- Archivos: `bootstrap/app.php`, `AppointmentController.php`, `AppointmentAvailabilityRequest.php`, `AppointmentDetailsDialog.vue`, `Appointments/Index.vue`, `AppointmentAvailabilityTest.php`, `AppointmentDialogStructureTest.php` y este plan. Sin migraciones, permisos o dependencias.
- Verificación directa: `php artisan test --filter=Appointment` detectó 68 pruebas: 4 correctas y 64 errores; `php artisan test` detectó 156 pruebas: 5 correctas y 151 errores. Todos los errores son `could not find driver` por SQLite global deshabilitado; esas ejecuciones no se declaran correctas.
- Verificación con SQLite por proceso: Agenda 68 pruebas y 422 aserciones correctas; suite completa 156 pruebas y 1,049 aserciones correctas. No se crearon archivos temporales. `php artisan optimize:clear` y Pint correctos.
- Build: `npm run build` correcto con 1,199 módulos. Persiste la advertencia no bloqueante por chunks mayores de 500 kB.
- Pruebas manuales pendientes: abrir una cita y confirmar fecha/hora/asignaciones precargadas y loading; conservar la hora propia y excluir otra ocupada; cambiar fecha y persona como owner/administrator; probar cita propia y compartida como employee; provocar sin fecha, sin slots, 422, 403 y 404; cerrar por X, botón, Escape y exterior desde detail; comprobar que edit/reschedule y operaciones activas bloquean cierre; cerrar desde cada modo, reabrir y confirmar detail sin errores residuales; revisar desktop, tablet y móvil.
- Límite: no se implementaron cancelación, no-show, adelantos, ventas desde citas, proyección ni funcionalidad de Fase 4B.

#### Resultado del Ajuste 3 de 3 — Prompt 2 de 2 (2026-07-20)

- Estado: Prompt 1 queda `Aprobado / Sí` por confirmación manual. Prompt 2 y Ajuste 3 global quedan `En pruebas / No`. Fase 4A permanece `En pruebas / No`, sin aprobación global; Fase 4B permanece `Pendiente / No`.
- Navegación: se eliminaron los controles visibles `Vista Mes` y `Vista Día`. `/appointments` abre el calendario; tocar un día abre su Agenda cronológica; `Volver al calendario` conserva mes, día seleccionado, filtro y scroll mediante Inertia, `preserveState` y `preserveScroll`. Anterior/siguiente permanece dentro de Vista Día.
- Encabezado: existe un solo encabezado `Agenda`. El texto contextual indica selección de día en mes y `Citas del [fecha]` en día. Nueva cita permanece como acción principal; Hoy, navegación temporal y filtro autorizado viven en la barra contextual del único árbol activo.
- Visual: el calendario usa fondo cálido, bordes suaves, separación de seis píxeles, celdas uniformes, hover discreto, hoy circular, selección primary suave y días externos atenuados. Desktop muestra conteos y hasta dos actividades; tablet compacta previews; móvil conserva siete columnas y muestra número más badge, sin textos largos ni scroll horizontal.
- Transición: Vue `Transition` con `mode="out-in"` aplica opacidad, escala y desplazamiento durante 220 ms. No hay overlay, librería externa ni manejador de rueda. `prefers-reduced-motion: reduce` elimina desplazamiento y reduce la duración a 1 ms.
- Rendimiento: el controlador ejecuta únicamente la consulta mensual cuando `view=month` y únicamente la consulta diaria cuando `view=day`; el payload de la vista inactiva queda vacío. No se montan calendario y Agenda simultáneamente y la animación no dispara solicitudes adicionales.
- Privacidad: no cambian Resources ni scopes. Employee continúa recibiendo solo sus `appointment_items`; owner y usuarios `view_all` conservan filtro y detalle permitido; citas compartidas no revelan personas ajenas.
- Archivos: se modificaron `AppointmentController.php`, `Appointments/Index.vue`, `AppointmentCalendarTest.php`, `Phase4AAppointmentTest.php`, `Phase4AAppointmentUpdateTest.php` y este plan. No hubo migraciones, permisos ni dependencias nuevas.
- Verificación: `php artisan optimize:clear`, `php artisan migrate` (`Nothing to migrate`) y `php artisan db:seed` correctos. Directas bloqueadas por SQLite global: filtro Appointment 64 detectadas, 2 correctas y 62 errores; suite completa 152 detectadas, 3 correctas y 149 errores `could not find driver`. Con extensiones SQLite por proceso: filtro Appointment 64/391 y suite completa 152/1018 correctas. `npm run build` correcto con 1,199 módulos; permanece advertencia no bloqueante por chunks mayores de 500 kB. Pint correcto.
- Pruebas manuales pendientes: verificar entrada mensual, selección/retorno y transición en ambos sentidos; recargar URLs de mes/día con filtro; probar anterior/siguiente/Hoy; revisar privacidad y compartidas como employee; activar reducción de movimiento; comprobar 1440x900, 1024x768, 768x1024 y 390x844.
- Riesgos: no hay E2E de navegador ni validación visual automatizada; durante navegación Inertia se conserva el árbol anterior hasta recibir la respuesta y luego inicia la transición; el bundle supera 500 kB. No se implementaron cancelación, no-show, adelantos, ventas desde citas o proyección.

#### Resultado del Ajuste 3 de 3 — Prompt 1 de 2 (2026-07-20)

- Estado: Ajuste 1 y Ajuste 2 quedan `Aprobado / Sí` por confirmación manual. Ajuste 3, Prompt 1 queda `En pruebas / No`; no aprueba Ajuste 3 ni Fase 4A. Fase 4B permanece `Pendiente / No`.
- Arquitectura: la única ruta `/appointments` admite `view=month|day`, `month=YYYY-MM`, `date=YYYY-MM-DD` y `employee_id` opcional. Vista Mes y Vista Día usan navegación Inertia con `preserveState`; Volver al mes conserva mes y filtro.
- Consulta: `BuildAppointmentCalendarAction` usa `appointment_items` como fuente de segmentos, persona y servicio. Consulta solamente los items del mes `scheduled`, sus cabeceras mínimas y una agregación de personas distintas por cita; no carga eventos, notas ni Resources completos, y evita N+1.
- DTO mensual: cada día con actividad devuelve `date`, `appointments_count` por IDs distintos, `services_count`, `has_appointments` y hasta dos `previews`. Cada preview contiene solo `appointment_id`, inicio/fin visible, hora, servicio, clienta autorizada, `assigned_name` solo con `view_all` e `is_shared`.
- Privacidad: employee recibe exclusivamente sus items y horarios. No recibe nombres, segmentos, importes, eventos o notas ajenas; una cita compartida conserva solo la etiqueta. Owner o usuario con `view_all` recibe todas las previews y puede filtrar por persona activa con `appointments.perform`; sin `view_all`, fabricar `employee_id` se rechaza.
- UI: cuadrícula mensual de siete columnas con días atenuados fuera del mes, día actual/seleccionado, contador de citas/servicios, hasta dos previews y `+X más`. Móvil conserva calendario compacto con número y contador, sin scroll horizontal. Seleccionar un día abre Vista Día existente bajo los mismos scopes.
- Validación: `AppointmentsIndexRequest` valida vista, mes, fecha y persona. No hay rutas, datos ni acciones de cancelación, no-show, adelantos, ventas o proyección.
- Verificación: `php artisan optimize:clear`, `php artisan migrate` (`Nothing to migrate`) y `php artisan db:seed` correctos. Directas bloqueadas por SQLite global: filtro Appointment 62 detectadas, 2 correctas y 60 errores; suite completa 150 detectadas, 3 correctas y 147 errores `could not find driver`. Con extensiones SQLite por proceso: filtro Appointment 62/358 y suite completa 150/985 correctas. `npm run build` correcto con 1,199 módulos y advertencia no bloqueante de chunks mayores de 500 kB. Pint correcto.
- Pruebas manuales pendientes: cambiar mes, Hoy y seleccionar días como owner/administrator/employee; comprobar previews, conteos, compartidas, filtro y URL; abrir día y volver al mes conservando filtro; revisar 1440x900, 1024x768, 768x1024 y 390x844.
- Riesgos: no hay E2E de navegador ni medición SQL de carga sobre un mes de volumen real; la agenda diaria con filtro de employee queda limitada a los items coincidentes, sin un DTO diario separado; bundle mayor de 500 kB. Prompt 2 conserva animaciones y gestos pendientes.

#### Resultado del Ajuste 2 de 3 (2026-07-20)

- Estado: Ajuste 1 permanece `En pruebas / No`; Ajuste 2 queda `En pruebas / No`; Ajuste 3 permanece `Pendiente / No`. Fase 4A sigue `En pruebas / No` y no esta aprobada.
- Decision: APT-D001 queda `Sustituida`. APT-D024, APT-D025, APT-D026 y APT-D027 quedan aprobadas: cada item tiene persona propia, duracion reservada propia, ejecucion consecutiva por `position` y opcion de una persona comun.
- Migracion aplicada: `2026_07_20_101000_add_segments_to_appointment_items_table.php`, batch 6. Agrega `assigned_to`, `position`, `scheduled_start`, `scheduled_end` y `default_duration_minutes`; conserva `duration_minutes` como duracion reservada. Agrega indices por persona, inicio, fin y cita/posicion. La migracion agrega indices simples de cita/servicio antes de retirar la unicidad historica porque MySQL los necesita para las claves foraneas.
- Backfill: cada item historico recibe la persona temporal de `appointments.assigned_to`, duracion habitual igual a su snapshot previo, posicion estable, inicio desde la cabecera y final consecutivo. No se elimina ni recrea ninguna cita. `appointments.assigned_to` sigue siendo el primer item solo por compatibilidad, no por scope o conflicto.
- Contrato de creacion: solo `items[]` con `service_id`, `assigned_to`, `quantity` y `duration_minutes`; no hay persona global autoritativa. La duracion es 5-480 minutos en intervalos de cinco, no modifica `services.duration_minutes`, y el backend conserva precio y snapshots de catalogo.
- Contrato de reprogramacion: solo `date`, `start_time`, `reschedule_note` y `assignments[]` opcional con `appointment_item_id` y `assigned_to`. No acepta servicios, cantidades, duraciones, precios o items completos. IDs ajenos a la cita se rechazan; sin `appointments.assign` cualquier asignacion se rechaza.
- Reprogramacion: bloquea cabecera e items ordenados, valida estado y scope por item, bloquea personas implicadas por ID, valida actividad/`appointments.perform`, conserva todos los snapshots/cantidades/duraciones/posiciones, recalcula segmentos consecutivos y sincroniza la cabecera y su persona legacy con el primer item. Todo ocurre en una transaccion auditada.
- Conflictos y scopes: la fuente autoritativa es `appointment_items`. Conflictos usan intervalos semiabiertos por persona y segmento contra items de citas `scheduled`, excluyendo la cita propia. Employee ve, detalla, edita informacion y reprograma una cita si participa en cualquier item; no depende de la cabecera legacy.
- Frontend: Nueva cita emite items por servicio, switch de persona comun, selector por item, duracion reservada, cantidad y vista de segmentos. Detalle muestra personal participante y cada segmento. Reprogramar mantiene un solo dialogo, muestra servicios en lectura y envia solo `assignments` para usuarios autorizados.
- Auditoria: eventos `created` registran servicio, persona, duracion habitual/reservada y segmento. Reprogramaciones muestran fecha/hora/final y cambios de persona por servicio con nombres legibles; no se exponen IDs o JSON tecnico.
- Fallos encontrados y corregidos: las pruebas heredadas enviaban `assigned_to` y `services`, mientras las acciones esperaban segmentos; se migraron a `items` y `assignments`. La migracion inicialmente no podia retirar la unicidad historica porque respaldaba FKs MySQL; se agregaron indices de respaldo. Reprogramacion comparaba el nuevo final contra la fecha anterior, produciendo un falso cruce de medianoche; ahora compara inicio y final nuevos. Una prueba estructural aun esperaba el texto de Prompt 1 y fue actualizada al contrato de assignments.
- Verificacion: `php artisan optimize:clear` correcto. `php artisan migrate:status` confirma la migracion batch 6 como aplicada. `php artisan test --filter=Appointment` directo detecto 50 pruebas, 2 correctas y 48 errores `could not find driver`; no se considera exitoso. Con extensiones SQLite por proceso, la suite dirigida paso 48/254 y la suite completa paso 138/894. No se crearon archivos temporales persistentes.
- Build: `npm run build` correcto con 1,199 modulos; permanece la advertencia no bloqueante por chunks mayores de 500 kB.
- Pruebas manuales pendientes: crear dos servicios con la misma y con distintas personas; modificar solo la duracion reservada; verificar segmentos, horarios simultaneos por personas distintas, adyacencia y conflicto por misma persona; comprobar agenda y detalle para employee participante del segundo item; reprogramar sin assignments como employee y con uno/varios assignments como owner/admin; probar ID ajeno, rollback, historial y responsive 1440x900, 1024x768, 768x1024 y 390x844.
- Riesgos: no hay E2E de navegador real ni carrera multiproceso MySQL; SQLite sigue deshabilitado globalmente; dos formularios pueden confirmar cambios secuenciales sin version optimista; el bundle excede 500 kB. No se implementaron calendario, cancelacion, no-show, adelantos, ventas o proyeccion.

#### Correccion de privacidad por segmentos (2026-07-20)

- Estado: esta correccion pertenece al Ajuste 2, que se mantiene `En pruebas / No`. Ajuste 3 permanece `Pendiente / No`; Fase 4A sigue `En pruebas / No` y no esta aprobada.
- Visibilidad: usuarios con `appointments.view_all` reciben y visualizan la cita completa, todos los items, personas, horarios, duracion y total. Usuarios con solo `appointments.view_own` reciben unicamente sus `appointment_items`; no se serializan items completos, horario completo, total completo ni persona legacy en otra propiedad.
- Payload: `AppointmentResource` y detalle exponen `is_shared`, `visible_start`, `visible_end`, `visible_duration_minutes`, `visible_total` y `visible_items`. Para employee los valores visibles se calculan exclusivamente desde sus items; para `view_all` representan la cita completa. Las cards y el detalle consumen solo estas propiedades.
- Cita compartida: se muestra la etiqueta `Cita compartida` sin identificar otras personas. Los segmentos propios separados se mantienen como bloques de ocupacion independientes; no se representa al employee ocupado durante pausas que pertenecen a otra persona.
- Historial: `view_all` conserva el historial completo. Employee recibe cambios de sus items y cambios generales autorizados; reasignaciones y nombres ajenos se eliminan. Cuando un evento no tiene detalle seguro muestra `Se actualizó la cita`.
- Reprogramacion: dentro de la transaccion, una cita con items de otras personas exige `appointments.assign`. Employee sin ese permiso solo puede reprogramar si todos los items son propios; en una compartida recibe `Esta cita incluye servicios de otras personas. Solicita a un responsable que la reprograme.` La UI muestra el mismo mensaje y no entrega IDs ajenos.
- Ocupacion futura: Agenda usa `appointment_items.scheduled_start` y `appointment_items.scheduled_end` para los segmentos visibles de employee, no el rango completo de `appointments`. Esto prepara el calendario posterior sin implementarlo.
- Verificacion: `php artisan optimize:clear`, `php artisan migrate` (`Nothing to migrate`) y `php artisan db:seed` correctos. Las pruebas directas fallaron por SQLite global: filtro Appointment 55 detectadas, 2 correctas y 53 errores; suite completa 143 detectadas, 3 correctas y 140 errores, todos `could not find driver`. Con extensiones SQLite cargadas por proceso mediante `vendor/bin/phpunit`, filtro Appointment paso 55/317 y suite completa 143/944. Pint paso. `npm run build` paso con 1,199 modulos y mantiene advertencia no bloqueante de chunks mayores de 500 kB.
- Pruebas manuales pendientes: como employee abrir Agenda y detalle de una cita compartida, confirmar que solo aparecen servicios, horarios y subtotal propios; comprobar dos segmentos propios separados por otro empleado; confirmar etiqueta compartida sin nombres ajenos; intentar reprogramar compartida como employee y una cita completamente propia; repetir como owner y administrator; revisar desktop/tablet/movil en 1440x900, 1024x768, 768x1024 y 390x844.
- Riesgos: no hay E2E de navegador ni prueba de concurrencia MySQL; SQLite continua deshabilitado globalmente; eventos antiguos sin IDs de items solo pueden mostrar el mensaje seguro; el bundle supera 500 kB. No se implementaron calendario mensual, cancelacion, no-show, adelantos, ventas ni proyeccion.

#### Correccion de autoasignacion y disponibilidad (2026-07-20)

- Estado: pertenece al Ajuste 2, que se mantiene `En pruebas / No`. Ajuste 3 permanece `Pendiente / No`; Fase 4A sigue `En pruebas / No` y no esta aprobada.
- Employee: quien tiene `appointments.perform` y `appointments.create`, sin `appointments.assign`, crea todos los servicios para si mismo. Nueva cita no muestra switch ni selectores de persona y comunica `Tú realizarás los servicios de esta cita`. El backend rechaza cualquier `assigned_to` fabricado para otro usuario; no reasigna silenciosamente.
- Asignacion: `appointments.assign` habilita switch de persona comun, selector general y selector por servicio. Owner y administrator autorizados conservan asignacion por item.
- Horario operativo: se agrego `config/appointments.php`, con `APPOINTMENTS_OPEN_TIME=08:00`, `APPOINTMENTS_CLOSE_TIME=18:00` y `APPOINTMENTS_SLOT_MINUTES=15`. Son valores temporales pendientes del horario real de Studio Lemus; Actions los usan centralmente.
- Disponibilidad: `BuildAppointmentAvailabilityAction` interpreta Honduras, simula segmentos consecutivos, aplica el intervalo semiabierto por persona contra `appointment_items`, excluye la cita propia al reprogramar y devuelve solo inicios cuyo final es menor o igual al cierre. El POST final conserva validacion transaccional de conflictos y ahora tambien valida horario operativo.
- Ruta: `POST /appointments/availability`, nombre `appointments.availability`, autenticada y protegida por `appointments.access`. No guarda datos; devuelve `available_times`, `has_availability`, `operating_open_time` y `operating_close_time`.
- Interfaz: Nueva cita y Reprogramar usan selectores de horas disponibles con debounce, loading y limpieza de una hora previa invalida. Sin servicios muestra instruccion; sin slots muestra `No hay horarios disponibles para esta configuración`, deshabilita confirmar y permite marcar el dia como sin disponibilidad al abrirlo. No se construyo calendario mensual.
- Verificacion: `php artisan optimize:clear`, `php artisan migrate` (`Nothing to migrate`) y `php artisan db:seed` correctos. Pint correcto. Pruebas directas bloqueadas por SQLite global: filtro Appointment 60 detectadas, 2 correctas y 58 errores; suite completa 148 detectadas, 3 correctas y 145 errores, todos `could not find driver`. Con extensiones SQLite cargadas por proceso mediante `vendor/bin/phpunit`, filtro Appointment paso 60/336 y suite completa 148/963. `npm run build` correcto con 1,199 modulos y advertencia no bloqueante de chunks mayores de 500 kB.
- Pruebas manuales pendientes: employee crea con un intento fabricado de otra persona y confirma que no ve controles; owner/admin asignan distintos servicios; cambiar fecha, persona, cantidad y duracion actualiza slots; verificar ocupado, libre para otra persona, adyacente, cierre, dia lleno y reprogramacion propia/compartida; revisar 1440x900, 1024x768, 768x1024 y 390x844.
- Riesgos: no hay E2E de navegador ni carrera multiproceso MySQL; SQLite sigue deshabilitado globalmente; el date picker actual comunica dia sin disponibilidad tras consultar, sin deshabilitar anticipadamente todos los dias del mes; bundle mayor de 500 kB. No se implementaron calendario mensual, cancelacion, no-show, adelantos, ventas ni proyeccion.

#### Resultado del Ajuste 1 de 3 (2026-07-20)

- Alcance: se corrigieron exclusivamente modales de detalle/edicion/reprogramacion, los contratos de edicion y reprogramacion, y la lectura del historial. No se iniciaron Ajuste 2 ni Ajuste 3; Fase 4A se mantiene `En pruebas / No` y no esta aprobada.
- Dialogo: `AppointmentDetailsDialog` ahora es el unico `VDialog` del flujo con estados internos `detail`, `edit` y `reschedule`. Comparte overlay, card, header, cierre, contenido con scroll y footer. Desktop usa maximo 780 px y 85vh; movil es fullscreen. Volver conserva el detalle cargado sin otra consulta.
- Edicion: `PUT` acepta solo `client_name`, `client_phone` y `notes`. Ya no muestra ni valida persona, servicios, cantidades, precios, total, duracion o estado.
- Reprogramacion: `POST` acepta solamente `date`, `start_time`, `assigned_to` opcional y `reschedule_notes`. Employee conserva su persona; owner/administrator con `appointments.assign` pueden reasignar. Servicios, cantidades, snapshots, duracion y total quedan inmutables. El nuevo final se calcula con la duracion reservada y conserva validaciones de persona activa/perform, pasado, intervalos, medianoche, conflicto semiabierto y transaccion.
- Auditoria: cambios nuevos guardan persona como nombre e ID controlado; el Resource entrega titulo, actor, fecha Honduras, motivo y cambios `etiqueta: valor anterior -> valor nuevo`. Fecha, hora, finalizacion, nombre, telefono, notas y persona se formatean sin JSON, IDs, UTC o columnas tecnicas. Eventos historicos incompletos muestran que el detalle anterior no esta disponible. El historial se ordena del mas reciente al mas antiguo y los eventos siguen append-only.
- Migraciones: ninguna. `php artisan migrate` respondio `Nothing to migrate`.
- Verificacion: `php artisan optimize:clear` y `php artisan db:seed` terminaron correctamente. Pint paso. La suite dirigida paso con 24 pruebas y 133 aserciones; con SQLite cargado por proceso la suite completa paso con 138 pruebas y 893 aserciones. `php artisan test` directo descubrio 138 pruebas: 3 pasaron y 135 fallaron por `could not find driver`; SQLite continua deshabilitado globalmente.
- Build: `npm run build` correcto con 1,199 modulos transformados. Persiste la advertencia no bloqueante por chunks mayores de 500 kB.
- Pruebas manuales pendientes: abrir detalle y alternar editar/reprogramar para confirmar un unico overlay y Volver sin recarga; editar nombre/telefono/notas; comprobar que no hay servicios editables; reprogramar fecha/hora propia y reasignar solo como owner/admin; fabricar servicios, cantidades, total y persona como employee; validar conflicto, adyacencia, pasado, intervalos y medianoche; revisar historial largo, nombres, telefono vacio, notas, motivo y responsive en 1440x900, 1024x768, 768x1024 y 390x844.
- Riesgos: no hay prueba E2E de navegador real, solo una prueba estructural del componente para el unico dialogo; no se ejecuto una carrera multiproceso MySQL; dos formularios abiertos pueden confirmar cambios secuenciales sin version optimista; eventos historicos sin valores completos solo pueden mostrar el mensaje de respaldo; SQLite sigue sin carga global y el bundle supera 500 kB.
- Limite confirmado: no se implementaron varias personas, asignacion por servicio, duracion personalizada por servicio, calendario mensual, vista semanal, cancelacion, no-show, adelantos ni integracion con ventas.

### Resultado de Fase 4A - Prompt 1 de 2 (2026-07-20)

- Documentacion: este plan fue creado como fuente oficial separada y `docs/STUDIO_LEMUS_IMPLEMENTATION_PLAN.md` contiene una referencia breve sin modificar estados 3A-3E. APT-D001 a APT-D006, APT-D018, APT-D019 y APT-D020 quedaron `Aprobada`; las demas decisiones permanecen `Propuesta`.
- Migraciones: `2026_07_20_100000_create_appointments_table.php`, `2026_07_20_100100_create_appointment_items_table.php` y `2026_07_20_100200_create_appointment_events_table.php` fueron aplicadas en MySQL como batch 5. Son nuevas, reversibles y no modifican migraciones anteriores.
- Datos: `appointments` conserva clienta, asignacion, inicio/fin UTC, total/duracion, estado y campos terminales preparados. `appointment_items` conserva snapshots. `appointment_events` registra auditoria append-only. No hay soft deletes, ruta de eliminacion o mutacion de estados; el modelo rechaza eliminacion fisica.
- Modelos: `Appointment`, `AppointmentItem` y `AppointmentEvent` estan protegidos contra asignacion masiva, tienen casts y relaciones explicitas. `User` agrega citas asignadas/creadas y eventos realizados; `Service` agrega items de cita.
- Permisos: se crearon exclusivamente `appointments.access`, `appointments.view_own`, `appointments.view_all`, `appointments.create` y `appointments.perform`. Owner recibe los cinco; administrator recibe access/own/all/create; employee recibe access/own/create/perform. Los seeders son idempotentes y administrator no recibe `perform` por defecto.
- Backend: `CreateAppointmentAction` autoriza, fuerza scope propio para usuarios sin `view_all`, interpreta Honduras, rechaza pasado e intervalos fuera de 15 minutos, bloquea persona y servicios ordenados, confirma actividad/`perform`, consolida lineas, calcula en centavos, rechaza cruce de medianoche, valida solapamiento semiabierto y crea cita/items/evento en una transaccion con tres intentos.
- Autoridad: el navegador no controla fin, duracion, precio, total, estado o creador. `StoreAppointmentRequest` solo valida datos de clienta, fecha, hora, persona, servicios/cantidades y notas, con mensajes en espanol.
- Rutas: existen exclusivamente `GET /appointments` (`appointments.index`) y `POST /appointments` (`appointments.store`) dentro de `auth`, `active` y middleware granular. No existen endpoints de show, update, delete, reprogramacion, cancelacion, no-show, adelanto o checkout.
- Agenda: `Appointments/Index` usa fecha Honduras por query string, controles Dia anterior/Hoy/Dia siguiente, selector, lista cronologica, cards con clienta/telefono/servicios/persona/duracion/total/estado/notas, loading, estado vacio y CTA autorizado. Backend entrega todas las citas a `view_all` y solo las propias a employee.
- Nueva cita: dialogo responsive y fullscreen movil, persona elegible, servicios multiples activos, cantidades 1-20, resumen informativo de duracion/fin/total, errores por campo, persistencia ante validacion y bloqueo de doble envio/cierre externo durante proceso.
- Navegacion: Agenda aparece solo con `appointments.access`, despues de Nueva venta y antes de Ganancias Generales. Owner conserva Inicio/Nueva venta/Agenda/Ganancias/Configuracion; employee conserva Nueva venta/Agenda.
- Migracion y seed reales: `php artisan optimize:clear`, `php artisan migrate`, `php artisan db:seed`, `php artisan route:list` y `php artisan migrate:status` terminaron correctamente. MySQL conserva cero citas, items y eventos; no se insertaron datos demo.
- Pruebas directas: `php artisan test` descubrio 114 pruebas; 1 paso y 113 terminaron con `could not find driver` porque SQLite continua deshabilitado globalmente. No se afirma que esa ejecucion paso.
- Pruebas con extensiones por proceso: las 26 pruebas de Agenda pasaron con 133 aserciones y la suite completa paso con 114 pruebas y 760 aserciones. No se creo INI o directorio temporal. La cobertura incluye autorizacion, scopes, asignacion, snapshots, calculos, manipulacion, horarios, solapamiento, auditoria, rollback posterior al evento, reversibilidad, inmutabilidad, seeders y ausencia de rutas futuras.
- Build: `npm run build` correcto con 1,196 modulos transformados. Persiste la advertencia no bloqueante por chunks mayores de 500 kB. Pint pasa en todos los archivos PHP intervenidos.
- Pruebas manuales pendientes: crear y consultar citas reales con owner/administrator/employee; validar lista propia/todas, servicios multiples, conflictos y adyacencia; revisar errores y doble envio; comprobar desktop/tablet/movil en 1440x900, 1024x768, 768x1024 y 390x844 sin scroll horizontal.
- Riesgos: SQLite no carga por defecto; la carrera multiproceso de MySQL no fue ejecutada aunque el bloqueo por persona esta implementado; no existen horarios laborales o disponibilidad distinta de solapamientos; servicios pueden eliminarse y la cita dependera de snapshots; no hay pruebas frontend/E2E; el bundle supera 500 kB.
- Limite confirmado: no se implementaron detalle, edicion, reprogramacion, cancelacion, no-show, adelantos, devoluciones, pagos, integracion con ventas, proyeccion, vista semanal, recordatorios o clientes independientes. Falta Fase 4A Prompt 2 de 2: detalle y reprogramacion.

### Resultado de Fase 4A - Prompt 2 de 2 (2026-07-20)

- Estado: Prompt 1 permanece `Aprobado / Si` por confirmacion manual del usuario. Prompt 2 y Fase 4A global quedan `En pruebas / No`; solo el usuario puede aprobar la fase completa. Fase 4B permanece `Pendiente / No`.
- Migraciones: no se crearon ni modificaron migraciones. `php artisan migrate` respondio `Nothing to migrate`; las tablas existentes de 4A Prompt 1 son suficientes.
- Permisos: se crearon exclusivamente `appointments.update` y `appointments.assign`. Owner y administrator reciben ambos; employee recibe `appointments.update` pero no `appointments.assign`. Los seeders se ejecutan idempotentemente.
- Rutas: se agregaron `GET /appointments/{appointment}` (`appointments.show`), `PUT /appointments/{appointment}` (`appointments.update`) y `POST /appointments/{appointment}/reschedule` (`appointments.reschedule`). No existen rutas de cancelacion, no-show, adelanto, checkout o eliminacion.
- Detalle: endpoint JSON autorizado con clienta, telefono, fecha, horario, persona, snapshots reservados, duracion, total, notas, estado, creador, fecha de creacion e historial basico. Employee recibe 403 para citas ajenas; owner/administrator con `view_all` consultan cualquiera.
- Interfaz: cada card incorpora `Ver detalle`. `AppointmentDetailsDialog` muestra el detalle y los eventos en un dialogo responsive/fullscreen movil. Solo citas `scheduled` y usuarios con `appointments.update` ven `Editar informacion` y `Reprogramar`; no se muestran acciones de fases posteriores.
- Edicion: `PUT` permite clienta, telefono, notas, servicios, cantidades y persona solo con `appointments.assign`. Employee puede editar exclusivamente citas propias y el backend rechaza una reasignacion fabricada. Fin, duracion, total, estado, creador y campos terminales nunca provienen de datos validados.
- Reprogramacion: `RescheduleAppointmentAction` delega en una mutacion transaccional compartida que bloquea la cita, vuelve a comprobar `scheduled` y scope, bloquea usuarios involucrados en orden ascendente, valida `perform`, bloquea servicios, interpreta Honduras, rechaza pasado/intervalos invalidos/medianoche, recalcula snapshots y reemplaza items.
- Solapamiento: vuelve a aplicar el intervalo semiabierto contra citas `scheduled`, excluye la propia cita y permite una cita exactamente adyacente. El cambio de persona se valida despues de bloquear tanto la asignacion anterior como la nueva.
- Snapshots: si los IDs y cantidades no cambian, una edicion basica conserva nombre, descripcion, duracion y precio reservados aunque cambie el catalogo. Cuando cambia la seleccion o cantidad, se usan servicios activos y snapshots actuales.
- Auditoria: `AppointmentEvent` agrega tipos `updated` y `rescheduled`. Cada evento conserva solo diferencias controladas, actor, hora UTC y nota opcional de reprogramacion. Los eventos no se editan ni eliminan. Cambios de horario, persona o duracion se clasifican como `rescheduled`; cambios basicos o de servicios sin alterar horario como `updated`.
- Transaccion: cita, items y evento se confirman juntos con tres intentos. Un fallo inducido despues de crear el evento revierte horario, asignacion, snapshots e historial completos.
- MySQL: `php artisan optimize:clear`, `php artisan db:seed` y `php artisan route:list` terminaron correctamente. La cita manual preexistente, sus dos items y su evento `created` permanecieron intactos; esta intervencion no creo ni modifico citas reales.
- Pruebas directas: `php artisan test` descubrio 135 pruebas; 1 paso y 134 terminaron con `could not find driver` porque SQLite sigue deshabilitado globalmente. No se declara esa ejecucion como correcta.
- Pruebas con extensiones por proceso: las 47 pruebas acumuladas de Agenda pasaron con 241 aserciones y la suite completa paso con 135 pruebas y 868 aserciones. No se creo configuracion temporal persistente. La cobertura incluye detalle, scopes, permisos, reasignacion, estados, recalculos, snapshots, pasado, intervalos, medianoche, conflicto propio/ajeno, adyacencia, rollback, eventos, inmutabilidad, manipulacion, rutas y seeders.
- Build: `npm run build` correcto con 1,202 modulos transformados. Persiste la advertencia no bloqueante por chunks mayores de 500 kB. Pint pasa en todos los archivos PHP intervenidos.
- Pruebas manuales pendientes: abrir detalle como owner/administrator/employee; comprobar 403 ajeno; editar clienta/telefono/notas/servicios; cambiar y conservar cantidades; reasignar como owner/admin y fabricar reasignacion como employee; reprogramar a horario libre, ocupado, adyacente, pasado, minuto invalido y cruce de medianoche; provocar errores y confirmar que el formulario conserva valores; revisar historial, doble envio y responsive en 1440x900, 1024x768, 768x1024 y 390x844.
- Riesgos: SQLite no carga globalmente; no se ejecuto una carrera multiproceso MySQL de dos reprogramaciones; dos formularios abiertos pueden aplicar cambios secuenciales sin control de version optimista, aunque ambos quedan auditados; un servicio eliminado deja `service_id` nulo y requerira seleccionar servicios vigentes para una mutacion posterior; no hay suite E2E frontend; el bundle supera 500 kB.
- Limite confirmado: no se implementaron cancelacion, no-show, adelantos, devoluciones, pagos, conversion a venta, relacion con `sales`, proyeccion, vista semanal, reactivacion o eliminacion fisica. No se avanzo a Fase 4B.

## Diagnostico del repositorio

- Laravel 13, Vue 3, Inertia, Vuetify, MySQL y Spatie Permission.
- Las rutas usan sesiones web, CSRF, `auth`, `active` y middleware granular de permisos.
- Ventas usa acciones transaccionales, bloqueo de servicios, snapshots, calculos en centavos e idempotencia.
- Servicios activos contienen nombre, descripcion, duracion y precio; pueden cambiar o eliminarse, por lo que las citas deben conservar snapshots.
- Laravel y MySQL almacenan timestamps en UTC. Los limites operativos y la interfaz usan `America/Tegucigalpa`.
- Diagnóstico histórico al crear el plan: no existían agenda, citas, clientes, adelantos, pagos parciales o calendario. La afirmación queda superada por 4A-4F: hoy existen Agenda, citas, calendario mensual, adelantos, pagos de venta e Historial; todavía no existe un modelo CRM de clientes.
- Ganancias Generales usa exclusivamente ventas `completed`; la proyeccion futura debe mantenerse separada.
- SQLite en memoria es la configuracion de pruebas, pero sus extensiones pueden estar deshabilitadas localmente. La concurrencia real requiere verificacion MySQL.

## Principios del modulo

1. La vista diaria es la interfaz principal del MVP.
2. El backend es autoridad para horario, duracion, precios, totales, estado, asignacion y permisos.
3. Una cita conserva snapshots historicos independientes de Servicios y Ventas.
4. No se eliminan citas fisicamente y no se usan soft deletes como estados.
5. Toda mutacion relevante produce auditoria append-only.
6. Dinero se calcula en centavos y se guarda en `decimal(12,2)`, nunca con `float` autoritativo.
7. Las citas se almacenan en UTC y se interpretan en `America/Tegucigalpa`.
8. Employee solo recibe datos de citas asignadas a su propio usuario.
9. Ocultar una opcion en Vue no sustituye autorizacion backend.
10. No se implementa funcionalidad de una fase posterior antes de su aprobacion.

## Alcance funcional del MVP

1. Crear cita.
2. Ver agenda diaria.
3. Reprogramar en el Prompt 2 de 4A.
4. Cancelar o marcar no llego en 4B.
5. Gestionar adelanto y devoluciones en 4C.
6. Atender y cobrar en 4D.
7. Consultar proyeccion separada en 4E.
8. Consultar Historial autorizado y detalle inmutable en 4F.

Fuera del MVP inicial: calendario mensual complejo, arrastrar y soltar, WhatsApp, notificaciones automaticas, CRM, historial medico o estetico, multiples sucursales, cabinas, comisiones de empleados, pagos divididos complejos, facturacion fiscal, listas de espera, recurrencias y reservas publicas.

## Arquitectura conceptual

### `appointments`

- Datos de la clienta.
- Persona asignada.
- Inicio y fin UTC.
- Total y duracion estimados como snapshots.
- Estado y actores de estados terminales.
- Sin borrado fisico.

Indices recomendados:

- `(assigned_to, status, scheduled_start)`.
- `(status, scheduled_start)`.

### `appointment_items`

Cada linea conserva `service_id` nullable y snapshots de nombre, descripcion, duracion, precio, cantidad y total. Cambiar o eliminar el servicio no reescribe la cita.

### `appointment_events`

Auditoria append-only con actor y hora del servidor. Registra creacion, reprogramacion, cambios, cancelacion, no-show, resolucion de adelanto y conversion a venta conforme se aprueben las fases. Los JSON no guardan contrasenas, permisos, tokens o datos sensibles.

### Adelantos futuros

`appointment_deposits` sera una tabla independiente con un adelanto maximo por cita en el MVP. Las devoluciones deben representarse con transacciones auditables, no sobrescribiendo silenciosamente el pago original.

### Venta futura

`sales.appointment_id` sera nullable y unico. Una cita tendra como maximo una venta. La cita y la venta conservaran snapshots independientes. Ninguna relacion con ventas se implementa en 4A.

## Estados

Estados aprobados para el ciclo completo:

- `scheduled`: programada y bloquea horario.
- `completed`: venta final confirmada; terminal y no bloquea horario.
- `canceled`: cancelada; terminal y no bloquea horario.
- `no_show`: la clienta no llego; terminal y no bloquea horario.

No se agregan `confirmed`, `checked_in` o `in_service` sin una necesidad real aprobada. En el Prompt 1 solo se crea `scheduled`.

## Horarios y solapamiento

- Las horas de inicio se seleccionan en intervalos de 15 minutos.
- La duracion total es la suma de `duration_minutes * quantity` de los servicios consolidados.
- La hora final se calcula en backend.
- Se rechaza una cita nueva en el pasado.
- Se rechaza una cita que termine en otro dia local.
- Se bloquea la fila de la persona asignada antes de consultar conflictos.
- Los servicios se bloquean en orden por ID y se vuelven a validar como activos.
- Solo `scheduled` bloquea horario en el alcance actual.

Formula semiabierta:

```text
new_start < existing_end
AND
new_end > existing_start
```

Una cita puede comenzar exactamente cuando termina otra. Toda futura reprogramacion debe repetir la validacion dentro de una transaccion.

## Interfaz

### Agenda diaria

Ruta `GET /appointments?date=YYYY-MM-DD`.

- Fecha seleccionada.
- Dia anterior, Hoy y Dia siguiente.
- Boton Nueva cita con permiso.
- Lista cronologica de cards.
- La lista operativa contiene exclusivamente citas `scheduled`; los cuatro estados se consultan en el Historial implementado en 4F.
- Hora, clienta, telefono, servicios, persona, estado, duracion, total estimado y notas resumidas.
- Estado vacio con CTA autorizado.

Desktop usa una lista cronologica espaciosa. Tablet mantiene cards comodas. Movil usa cards verticales, modal fullscreen y cero scroll horizontal. En 4F se descartó la vista semanal por instrucción expresa; no forma parte del alcance implementado.

### Nueva cita

- Nombre obligatorio.
- Telefono opcional pero recomendado.
- Fecha y hora.
- Persona asignada.
- Uno o varios servicios activos y cantidad.
- Notas opcionales.
- Resumen frontend informativo de duracion, hora final y total.

El backend siempre recalcula los valores. El modal conserva los campos ante errores, muestra mensajes junto al campo, evita doble envio y no se cierra externamente durante procesamiento.

## Permisos futuros

Permisos de todo el modulo propuesto:

- `appointments.access`.
- `appointments.view_own`.
- `appointments.view_all`.
- `appointments.create`.
- `appointments.update`.
- `appointments.assign`.
- `appointments.cancel`.
- `appointments.mark_no_show`.
- `appointments.convert_to_sale`.
- `appointments.manage_deposit`.
- `appointments.resolve_deposit`.
- `appointments.view_projection`.
- `appointments.perform`.

En el Prompt 1 se crean exclusivamente `access`, `view_own`, `view_all`, `create` y `perform`.

Matriz aprobada para el Prompt 1:

| Permiso | Owner | Administrator | Employee |
|---|:---:|:---:|:---:|
| `appointments.access` | Si | Si | Si |
| `appointments.view_own` | Si | Si | Si |
| `appointments.view_all` | Si | Si | No |
| `appointments.create` | Si | Si | Si |
| `appointments.perform` | Si | No por defecto | Si |

Una persona seleccionable debe estar activa y poseer el permiso persistido `appointments.perform`. No basta el nombre del rol ni el bypass de owner. Employee recibe solo sus citas y, al crear, queda asignado a si mismo aunque fabrique otro ID. Owner y administrator con `view_all` pueden crear para cualquier persona elegible.

## Pantallas por fase

| Nombre | Ruta | Datos y acciones | Desktop y movil | Fase |
|---|---|---|---|---|
| Agenda | `/appointments` | Dia autorizado, crear | Lista cronologica/cards | 4A Prompt 1 |
| Nueva cita | Modal en Agenda | Datos, servicios, persona, resumen | Dialog/fullscreen | 4A Prompt 1 |
| Detalle | Modal o drawer | Snapshots y eventos | Drawer/dialog | 4A Prompt 2 |
| Reprogramar | Modal | Fecha, hora, persona y servicios | Dialog/fullscreen | 4A Prompt 2 |
| Cancelar/no llego | Modal | Motivo y resolucion aplicable | Dialog/fullscreen | 4B |
| Atender y cobrar | `/sales/new?appointment=` | Venta precargada | Flujo de venta existente | 4D |
| Proyeccion | `/earnings` | Proyeccion separada | Cards/listas | 4E |
| Historial de citas | `/appointments/history` | Cuatro estados, filtros, detalle y comprobante autorizados | Tabla/cards | 4F |

## Arquitectura financiera futura

- Total estimado: suma de snapshots reservados.
- Adelanto: dinero recibido antes del servicio; no crea una venta.
- Saldo pendiente: total final menos adelanto valido aplicado.
- Venta final: valor completo de servicios realmente realizados.
- Proyeccion: valor esperado de citas `scheduled`.
- Un adelanto retenido se informa separado de servicios vendidos.
- Una devolucion no suma ingreso.
- La comision POS del adelanto se calcula solo sobre el adelanto y se conserva si el negocio realmente la pago.
- Proyeccion, venta real y movimientos de efectivo nunca se suman como si fueran el mismo concepto.

## Integracion futura con Nueva venta

La accion `Atender y cobrar` abrira `/sales/new?appointment={appointment}`. Precargara snapshots reservados, clienta, persona, adelanto y saldo; permitira agregar servicios y quitar uno reservado con confirmacion. La cita se marcara `completed` solo dentro de la transaccion que confirme la venta. Una restriccion unica en `sales.appointment_id`, bloqueo e idempotencia impediran doble venta.

## Casos limite obligatorios

1. La misma persona no puede recibir dos citas solapadas.
2. Dos usuarios compitiendo se serializan mediante el bloqueo de la persona.
3. Desactivar un servicio no cambia snapshots existentes; no puede seleccionarse en citas nuevas.
4. Cambiar precio o duracion no modifica citas existentes.
5. Una persona desactivada conserva historia, pero no puede recibir citas nuevas.
6. Creacion y reprogramacion en el pasado se rechazan.
7. Reprogramar a un espacio ocupado debe rechazarse en el Prompt 2.
8. Citas que cruzan medianoche se rechazan en el MVP.
9. Llegar antes o tarde no cambia automaticamente horario o estado.
10. Cancelacion y no-show son estados diferentes y pertenecen a 4B.
11. Devoluciones total/parcial y retencion pertenecen a 4C.
12. POS sobre adelanto se calcula sobre ese monto, no sobre la cita.
13. Servicios adicionales o retirados afectan la venta real, no el snapshot original.
14. Cerrar o fallar Nueva venta conserva la cita `scheduled`.
15. Doble clic o dos cobros generan como maximo una venta en 4D.
16. Una cita completada se excluye de proyeccion para evitar duplicacion.
17. Canceladas y no-show se excluyen de proyeccion; retenciones van separadas.
18. La zona horaria del navegador nunca define limites autoritativos.
19. Las URLs fabricadas se protegen con middleware y scope backend.

## Pruebas futuras

- Autorizacion y scopes propios/todos.
- Estado activo y permiso `perform`.
- Servicios activos y snapshots.
- Calculos monetarios en centavos.
- Timezone Honduras y almacenamiento UTC.
- Intervalos, pasado, medianoche y adyacencia.
- Solapamiento y concurrencia MySQL.
- Transacciones y rollback.
- Estados y auditoria append-only.
- Adelantos, POS, reembolsos y retenciones.
- Conversion a venta, unicidad e idempotencia.
- Proyeccion sin duplicar ingresos.
- Responsive en 1440x900, 1024x768, 768x1024 y 390x844.

## Registro de decisiones

| ID | Tema | Recomendacion | Motivo | Alternativas | Consecuencias | Estado |
|---|---|---|---|---|---|---|
| APT-D001 | Asignacion | Una persona por cita | Mantener el MVP simple | Dividir servicios entre personas | Todos los servicios comparten responsable | Sustituida |
| APT-D002 | Servicios | Uno o varios servicios | Representar citas reales | Un servicio por cita | Requiere items y suma de duraciones | Aprobada |
| APT-D003 | Intervalos | Inicio cada 15 minutos | Mayor flexibilidad | Cada 30 minutos | El fin puede no coincidir con intervalo | Aprobada |
| APT-D004 | Estados | `scheduled`, `completed`, `canceled`, `no_show` | Ciclo minimo completo | Estados intermedios | Solo `scheduled` bloquea inicialmente | Aprobada |
| APT-D005 | Solapamiento | Intervalo semiabierto y validacion transaccional | Permitir citas adyacentes y evitar carreras | Validacion frontend o inclusiva | Requiere bloqueo de persona | Aprobada |
| APT-D006 | Telefono | Opcional y recomendado | No bloquear clientas sin telefono | Obligatorio | Menos friccion, contacto no garantizado | Aprobada |
| APT-D007 | Cantidad de adelantos | Uno por cita | Simplificar conciliacion | Multiples adelantos | Limita pagos anticipados fraccionados | Aprobada |
| APT-D008 | Persistencia de adelanto | Tabla separada | No confundirlo con venta | Columnas en cita o venta parcial | Requiere integracion posterior | Aprobada |
| APT-D009 | Devoluciones | Transaccion independiente | Auditoria financiera | Sobrescribir adelanto | Agrega entidad financiera | Aprobada |
| APT-D010 | POS de adelanto | 4% sobre adelanto | Reflejar costo real | Sobre total de cita | Snapshot independiente | Aprobada |
| APT-D011 | Cobro | Accion `Atender y cobrar` | Evitar pregunta en ventas normales | Selector en cada venta | Flujo parte desde la cita | Aprobada |
| APT-D012 | Relacion venta | Una venta por cita | Evitar duplicados | Varias ventas | Requiere indice unico | Aprobada |
| APT-D013 | Aplicacion | Adelanto reduce saldo, no total | No duplicar ingresos | Venta solo por saldo | Venta conserva valor completo | Aprobada |
| APT-D014 | Adicionales | Permitir servicios adicionales | Reflejar trabajo real | Bloquear carrito | Venta puede diferir de cita | Aprobada |
| APT-D015 | Retirar reservados | Permitir con confirmacion | Flexibilidad con trazabilidad | Prohibir | Cita conserva snapshot original | Aprobada |
| APT-D016 | Proyeccion | Solo `scheduled` | Evitar mezclar real y esperado | Incluir completadas | Al completar deja la proyeccion | Aprobada |
| APT-D017 | Retencion | Ingreso separado de servicios | No es servicio vendido | Sumar como venta | Requiere reporte separado | Aprobada |
| APT-D018 | Scope employee | Solo citas asignadas a si mismo | Menor exposicion y control backend | Ver agenda completa | Backend fuerza scope y asignacion propia | Aprobada |
| APT-D019 | Vista | Diaria en el MVP | Responsive y sencilla | Semanal o mensual | Vista semanal se difiere | Aprobada |
| APT-D020 | Auditoria | `appointment_events` append-only | No perder cambios | Solo timestamps del registro | Cada mutacion relevante crea evento | Aprobada |
| APT-D024 | Asignacion por servicio | Cada `appointment_item` tiene su propia persona asignada | Una cita puede requerir personal distinto | Una persona por cita | Los scopes y conflictos se evalúan por segmento | Aprobada |
| APT-D025 | Duracion especial por cita | Duracion editable solo al crear, guardada como snapshot | Reflejar reservas reales sin cambiar el catalogo | Usar siempre la duracion del servicio | No se edita desde informacion o reprogramacion | Aprobada |
| APT-D026 | Servicios consecutivos | Los segmentos siguen `position` sin simultaneidad | Horario entendible y conflictos deterministas | Servicios simultaneos | El siguiente segmento inicia al terminar el anterior | Aprobada |
| APT-D027 | Persona comun opcional | Switch para aplicar una persona a todos los servicios | Rapidez en citas simples | Asignar cada linea siempre | La UI mantiene las asignaciones sincronizadas | Aprobada |
| APT-D028 | Transiciones terminales | Solo `scheduled` puede pasar una vez a `canceled` o `no_show`; no hay reactivación | Conservar un ciclo de estado determinista y auditable | Reactivar o repetir transición | Estado terminal, segunda transición rechazada y horario liberado | Aprobada |
| APT-D029 | Motivos terminales | Cancelar y marcar No llegó exigen motivo de 5 a 500 caracteres | Trazabilidad operativa sin texto vacío | Motivo opcional o sin límite | Se guardan razón, actor y hora del servidor | Aprobada |
| APT-D030 | Momento de no-show | `no_show` solo desde `scheduled_start`, evaluado en `America/Tegucigalpa` | Evitar marcar ausencia antes de la cita | Permitirlo anticipadamente | Backend autoritativo y acción deshabilitada antes de hora | Aprobada |
| APT-D031 | Scope terminal employee | Employee actúa solo si todos los items son propios; compartidas requieren usuario con `view_all` | Una transición afecta todos los segmentos | Permitir a cualquier participante | Owner/administrator visibles actúan sobre cualquiera; employee completamente propia | Aprobada |
| APT-D032 | Permisos de estados | Owner, administrator y employee reciben `cancel` y `mark_no_show`, con scope backend | Separar capacidad de alcance y permitir operación propia | Solo responsables o permiso único | Middleware granular y revalidación transaccional | Aprobada |
| APT-D033 | Acciones frecuentes | Reprogramar, Cancelar y No llegó viven en la card; el detalle es principalmente informativo | Reducir clics y duplicación | Todas las acciones dentro del detalle | La card comunica inmediatamente qué puede hacerse | Aprobada |
| APT-D034 | Acciones responsive | Desktop usa barra compacta; móvil usa Ver detalle y Más acciones | Evitar botones grandes o cuatro acciones horizontales | Misma barra en todos los tamaños | Presentación distinta con las mismas capacidades backend | Aprobada |
| APT-D035 | Diálogo operativo | Un solo diálogo con modo inicial directo, altura natural y scroll interno necesario | Evitar overlays, espacio vacío y pasos intermedios | Diálogo por acción o card con altura fija | Carga, error, detalle y formularios comparten shell y limpieza | Aprobada |
| APT-D036 | Estados terminales UX | Card atenuada, motivo resumido y solo consulta | No aparentar actividad ni ofrecer mutaciones | Ocultarlas o conservar botones bloqueados | Permanecen visibles como historia sin acciones falsas | Sustituida |
| APT-D037 | Scope compartido UX | Employee consulta citas compartidas, pero toda mutación exige que los items sean completamente propios | Una acción afecta datos comunes de la cita | Permitir edición básica por participante | Owner/administrator con view_all gestionan compartidas | Aprobada |
| APT-D038 | Agenda operativa | Agenda diaria y calendario muestran exclusivamente `scheduled`; terminales quedan para Historial futuro y detalle directo | Representar solo trabajo pendiente y liberar la línea al resolver una cita | Cards terminales atenuadas en Agenda | Backend filtra listas/conteos sin borrar datos ni auditoría | Aprobada |

## Prompts de ejecucion

### Fase 4A

Lee completamente este plan. Implementa solo el prompt autorizado de Fase 4A y verifica sus decisiones aprobadas. No avances a estados, adelantos, ventas o proyeccion. Ejecuta pruebas y build, deja el prompt en `En pruebas`, nunca `Aprobada`, no hagas `git add`, commit ni push y espera aprobacion.

### Fase 4B

Lee completamente este plan. Implementa solo cancelacion y no-show cuando 4A este aprobada y las decisiones requeridas esten aprobadas. Conserva auditoria, prueba estados y permisos, ejecuta build, deja 4B `En pruebas`, no autoapruebes ni uses Git y espera aprobacion.

### Fase 4C

Lee completamente este plan. Implementa solo adelantos, POS, devolucion total/parcial y retencion cuando 4B y las decisiones financieras esten aprobadas. No integres ventas ni proyeccion. Prueba dinero, auditoria y permisos, ejecuta build, deja 4C `En pruebas`, no autoapruebes ni uses Git y espera aprobacion.

### Fase 4D

Lee completamente este plan. Implementa solo `Atender y cobrar`, precarga, aplicacion del adelanto y venta unica cuando 4C y las decisiones de venta esten aprobadas. No agregues proyeccion. Prueba rollback, concurrencia e idempotencia, ejecuta build, deja 4D `En pruebas`, no autoapruebes ni uses Git y espera aprobacion.

### Fase 4E

Lee completamente este plan. Implementa solo proyeccion separada dentro de Ganancias Generales cuando 4D y sus decisiones esten aprobadas. No mezcles citas con ventas reales. Prueba periodos, timezone y duplicacion, ejecuta build, deja 4E `En pruebas`, no autoapruebes ni uses Git y espera aprobacion.

### Fase 4F

Fase ejecutada técnicamente el 2026-07-24: Historial y refinamientos de bajo riesgo quedaron `En pruebas / No`. No se agregó vista semanal ni recordatorios. Solo el usuario puede aprobarla después de las pruebas manuales.

## Mantenimiento

- Registrar aprobaciones con fecha sin borrar la decision original.
- Cambiar solo la fase o prompt intervenido.
- Registrar migraciones, permisos, pruebas, build, manuales y riesgos reales.
- Nunca afirmar que una prueba paso si no pudo ejecutarse.
- No reducir controles backend, concurrencia o auditoria para acelerar una fase.
- Detenerse al terminar cada prompt y esperar aprobacion.

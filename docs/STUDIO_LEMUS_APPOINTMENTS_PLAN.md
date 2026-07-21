# Plan de implementacion de Agenda y Citas

Fecha de creacion: 2026-07-20  
Estado del documento: Fuente oficial del modulo futuro de Agenda y Citas  
Alcance del producto: Un solo Studio Lemus, una ubicacion, HNL, interfaz en espanol y horario `America/Tegucigalpa`

Este documento gobierna exclusivamente Agenda y Citas. Complementa `docs/STUDIO_LEMUS_IMPLEMENTATION_PLAN.md`, pero no modifica los estados, dependencias o aprobaciones de las fases POS 3A-3E. Cada fase requiere aprobacion expresa y se implementa en intervenciones separadas.

## Tablero de progreso

| Fase | Nombre | Estado | Dependencias | Aprobacion |
|---|---|---|---|---|
| 4A | Agenda base | Aprobada | APT-D001 a APT-D006, APT-D018, APT-D019 y APT-D020 | Sí |
| 4B | Estados, cancelacion y no-show | En pruebas | 4A aprobada | No |
| 4C | Adelantos y devoluciones | Pendiente | 4B aprobada y decisiones financieras correspondientes | No |
| 4D | Atender y cobrar | Pendiente | 4C aprobada y decisiones de integracion con ventas | No |
| 4E | Proyeccion en Ganancias Generales | Pendiente | 4D aprobada y decisiones de proyeccion | No |
| 4F | Refinamientos | Pendiente | 4E aprobada | No |

Estados validos: `Pendiente`, `En desarrollo`, `En pruebas` y `Aprobada`. Solo el usuario puede marcar una fase como `Aprobada` despues de las pruebas manuales.

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
- No existe actualmente agenda, citas, clientes, adelantos, pagos parciales o calendario.
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
- La lista operativa contiene exclusivamente citas `scheduled`; estados terminales se reservan para un Historial futuro.
- Hora, clienta, telefono, servicios, persona, estado, duracion, total estimado y notas resumidas.
- Estado vacio con CTA autorizado.

Desktop usa una lista cronologica espaciosa. Tablet mantiene cards comodas. Movil usa cards verticales, modal fullscreen y cero scroll horizontal. La vista semanal se pospone a 4F y requiere necesidad real.

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
| APT-D007 | Cantidad de adelantos | Uno por cita | Simplificar conciliacion | Multiples adelantos | Limita pagos anticipados fraccionados | Propuesta |
| APT-D008 | Persistencia de adelanto | Tabla separada | No confundirlo con venta | Columnas en cita o venta parcial | Requiere integracion posterior | Propuesta |
| APT-D009 | Devoluciones | Transaccion independiente | Auditoria financiera | Sobrescribir adelanto | Agrega entidad financiera | Propuesta |
| APT-D010 | POS de adelanto | 4% sobre adelanto | Reflejar costo real | Sobre total de cita | Snapshot independiente | Propuesta |
| APT-D011 | Cobro | Accion `Atender y cobrar` | Evitar pregunta en ventas normales | Selector en cada venta | Flujo parte desde la cita | Propuesta |
| APT-D012 | Relacion venta | Una venta por cita | Evitar duplicados | Varias ventas | Requiere indice unico | Propuesta |
| APT-D013 | Aplicacion | Adelanto reduce saldo, no total | No duplicar ingresos | Venta solo por saldo | Venta conserva valor completo | Propuesta |
| APT-D014 | Adicionales | Permitir servicios adicionales | Reflejar trabajo real | Bloquear carrito | Venta puede diferir de cita | Propuesta |
| APT-D015 | Retirar reservados | Permitir con confirmacion | Flexibilidad con trazabilidad | Prohibir | Cita conserva snapshot original | Propuesta |
| APT-D016 | Proyeccion | Solo `scheduled` | Evitar mezclar real y esperado | Incluir completadas | Al completar deja la proyeccion | Propuesta |
| APT-D017 | Retencion | Ingreso separado de servicios | No es servicio vendido | Sumar como venta | Requiere reporte separado | Propuesta |
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

Lee completamente este plan. Implementa solo refinamientos expresamente aprobados cuando 4E este aprobada. Evalua vista semanal por necesidad real; no agregues recordatorios automaticos sin plan nuevo. Ejecuta pruebas y build, deja 4F `En pruebas`, no autoapruebes ni uses Git y espera aprobacion.

## Mantenimiento

- Registrar aprobaciones con fecha sin borrar la decision original.
- Cambiar solo la fase o prompt intervenido.
- Registrar migraciones, permisos, pruebas, build, manuales y riesgos reales.
- Nunca afirmar que una prueba paso si no pudo ejecutarse.
- No reducir controles backend, concurrencia o auditoria para acelerar una fase.
- Detenerse al terminar cada prompt y esperar aprobacion.

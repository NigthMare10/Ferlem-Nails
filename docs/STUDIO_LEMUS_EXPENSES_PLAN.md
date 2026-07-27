# Plan de Gastos y Nomina de Studio Lemus

Fecha de analisis: 2026-07-27
Estado del documento: Fases 6A-6E y reestructuracion automatica En pruebas / No
Alcance: Gastos manuales, nomina automatica integrada y resultado financiero; validacion manual pendiente

Este documento gobierna el futuro modulo de Gastos y Nomina. No cambia los estados, dependencias o aprobaciones de las fases existentes. Cada decision inicia como `Propuesta` y requiere aprobacion expresa antes de implementar la fase que dependa de ella.

## 1. Diagnostico del sistema actual

### Ganancias Generales

- Existe `GET /earnings`, protegido por `reports.sales.view`. El permiso se asigna por defecto al owner, no al administrator ni al employee.
- Soporta Hoy, Semana, Mes y Personalizado, con limites del dia operativo de Honduras (`America/Tegucigalpa`) convertidos a UTC mediante intervalos semiabiertos.
- Permite filtro por empleado y metodo de pago. En Ganancias, empleado significa ejecutor del servicio (`sale_items.performed_by`), no quien cobro la venta.
- Los resultados reales incluyen solo ventas `completed`; las ventas `canceled` quedan fuera de las cifras principales y se muestran como informacion secundaria.
- Actualmente calcula ingresos brutos, comision POS, ingreso posterior a POS, ventas, servicios, promedio, rendimiento por empleado, distribucion por metodo y desglose diario.
- El desglose diario implementado contiene solo dias con ventas, aunque documentos historicos mencionan dias en cero. Gastos por dia seguira expresamente el contrato solicitado: solo dias con gastos.
- El nombre comercial `Ganancias Generales` puede inducir a interpretar `net_amount` como utilidad. La UI actual aclara que el neto solo descuenta POS; al integrar Gastos se deben usar etiquetas y formulas explicitas.

### Ventas, pagos y comision POS

- `sales`, `sale_items` y `sale_payments` conservan snapshots inmutables. No se eliminan fisicamente.
- Los metodos actuales son efectivo, tarjeta y transferencia. Una venta de cita puede tener adelanto y pago final con metodos distintos.
- La tarjeta aplica actualmente una tasa POS del 4% calculada en centavos. Efectivo y transferencia tienen fee cero.
- La comision POS es un costo del procesador, no una comision laboral. No existe comision por empleado, salario, periodo de pago, bono, deduccion, anticipo ni liquidacion.
- En reportes por empleado, los importes se atribuyen por ejecutor de cada linea. En Facturas, el filtro de empleado usa el cobrador (`sales.sold_by`). El nuevo modulo debe rotular claramente `Empleado relacionado`, `Registrado por` y, cuando aplique, `Empleado pagado` para evitar esta ambiguedad.
- El filtro por metodo en Ganancias selecciona ventas que contienen ese metodo y no constituye una conciliacion monetaria por payment. No debe reutilizarse como base de nomina.

### Usuarios, roles y configuracion

- Existen roles fijos `owner`, `administrator` y `employee`, con Spatie Permission y permisos persistidos.
- Configuracion -> Usuarios permite listar, crear, editar identidad/rol, activar o desactivar y restablecer contrasena.
- `users` no contiene datos salariales. Agregar columnas salariales directamente mezclaria identidad/acceso con historial financiero.
- Los usuarios desactivados conservan sus relaciones historicas de ventas, citas y auditoria. El mismo principio debe aplicarse a gastos y pagos salariales.
- Owner recibe todos los permisos persistidos y un bypass de Gate. La navegacion usa permisos persistidos, por lo que cada permiso nuevo debe sincronizarse tambien con owner.
- Administrator no debe heredar acceso salarial por el nombre del rol. La autorizacion debe depender de permisos explicitos.

### Notificaciones

- Las notificaciones internas se almacenan en base de datos, se deduplican por destinatario/hecho y solo se entregan a usuarios activos autorizados.
- No se usa correo ni WhatsApp. Este patron es adecuado para Gastos y Nomina.
- Las notificaciones actuales se dirigen principalmente a owner/administrator con `notifications.access`. Las de nomina deben exigir ademas `payroll.view` para no filtrar informacion salarial.

### Comprobantes privados

- Las capturas de transferencia se almacenan en un disco privado, con nombre aleatorio, metadatos, MIME verificado y descarga/streaming por una ruta autorizada.
- No se publica la ruta fisica ni se usa un symlink publico.
- El patron actual acepta imagenes de hasta 5 MB. Gastos debe ampliarlo conceptualmente a JPG, PNG, WEBP y PDF, manteniendo almacenamiento privado y autorizacion especifica.
- No existe todavia una abstraccion generica de documentos privados, politica de retencion, antivirus o limpieza de huerfanos. Son riesgos a resolver durante 6A sin hacer publica la carpeta.

### Estado faltante

- No existen tablas, modelos, rutas, permisos, controladores, paginas ni pruebas de Gastos o Nomina.
- No existen categorias de gasto configurables.
- No existen obligaciones salariales pendientes.
- Ganancias no descuenta gastos y, por tanto, no muestra resultado disponible real.

## 2. Decision visual recomendada

### Modulo separado `Gastos`

Se recomienda una pantalla operativa independiente en `/expenses` para:

- Registrar gasto.
- Consultar y filtrar gastos.
- Abrir detalle.
- Ver comprobante privado.
- Anular con motivo.
- Consultar obligaciones salariales autorizadas.
- Marcar un salario como pagado.

Navegacion propuesta cuando cada opcion este implementada y autorizada:

1. Inicio.
2. Nueva venta.
3. Facturas.
4. Agenda.
5. Historial de citas.
6. Gastos.
7. Ganancias Generales.
8. Configuracion.

### Ganancias Generales como resumen

Ganancias no debe convertirse en CRUD de gastos. Debe limitarse a resumir:

- Ingresos brutos.
- Comision POS.
- Ingreso neto operativo.
- Gastos pagados.
- Resultado disponible.
- Gastos por categoria.
- Gastos por dia.
- Nomina pagada por empleado.
- Proyeccion existente.
- Obligaciones salariales pendientes.

Las acciones Crear, Anular, Categorias y Marcar salario como pagado pertenecen a Gastos o Configuracion, no al reporte.

## 3. Arquitectura financiera

Se deben conservar cuatro conceptos separados:

1. Venta: ingreso cobrado al cliente.
2. Comision POS: costo del procesador ya guardado como snapshot en ventas/pagos.
3. Gasto pagado: egreso real confirmado en `expenses` con estado `recorded`.
4. Obligacion: compromiso previsto que aun no representa salida real.

No se recomienda integrar Gastos con la tabla legada `cash_sessions`. El POS simplificado no usa apertura/cierre de caja y ligar el MVP a esa tabla reintroduciria un flujo sustituido. `payment_method` describe como se pago el gasto, pero no crea movimientos de caja en 6A-6E.

### Formulas formales

Para un mismo periodo Honduras y los mismos filtros autorizados:

```text
Ingresos brutos = SUM(sales.total)
  donde sales.status = completed

Comision POS = SUM(sales.card_fee_amount)
  donde sales.status = completed

Ingreso neto operativo = Ingresos brutos - Comision POS

Gastos pagados = SUM(expenses.amount)
  donde expenses.status = recorded
  y expense_date pertenece al periodo

Resultado disponible = Ingreso neto operativo - Gastos pagados
```

Reglas:

- Nunca incluir ventas `canceled` en ingresos reales.
- Nunca incluir gastos `canceled` en gastos pagados.
- Nunca incluir obligaciones `pending` dentro del resultado disponible.
- Un pago salarial reduce el resultado solo cuando existe su gasto de Nomina `recorded`.
- Los importes autoritativos se calculan en centavos o con decimales exactos; nunca `float`.
- El filtro por empleado del gasto usa `expenses.employee_id`. No debe confundirse con ejecutor de venta.
- Si se muestra `Otros compromisos pendientes`, debe provenir de una entidad futura real. En el MVP no se inventa ni se estima.

Advertencia obligatoria en Ganancias:

> El resultado disponible utiliza unicamente los gastos registrados y no sustituye un estado contable o fiscal.

`Resultado disponible` es preferible a `Ganancia neta`: no se calculan impuestos, depreciaciones, cuentas por pagar completas, inventario, obligaciones legales ni todos los conceptos contables.

## 4. Gastos manuales

### Flujo de registro

Campos:

- Fecha del gasto.
- Categoria.
- Descripcion.
- Monto.
- Metodo: Efectivo, Tarjeta o Transferencia.
- Proveedor o destinatario opcional.
- Empleado relacionado opcional.
- Nota opcional.
- Comprobante opcional.
- Usuario que registro, asignado por servidor.
- Fecha y hora del registro, asignadas por servidor en UTC.

Reglas recomendadas:

- `expense_date` representa el dia Honduras en que ocurrio/se pago el gasto; no es el timestamp de captura.
- Se permite una fecha pasada, con indicador visible `Registrado posteriormente` cuando difiere del dia de creacion.
- No se permite fecha futura para un gasto pagado. Los compromisos futuros pertenecen a obligaciones, no a `expenses`.
- Monto obligatorio mayor que cero, con maximo operativo documentado y validado.
- Categoria activa obligatoria al crear.
- Descripcion normalizada, entre 3 y 255 caracteres.
- Proveedor/destinatario, nota y nombre original del archivo tienen limites explicitos.
- Empleado es opcional para gastos generales y obligatorio para gastos creados desde un pago salarial.
- El backend ignora `recorded_by`, estados, anulador, rutas y metadatos enviados por navegador.
- El formulario usa un token idempotente unico para evitar doble clic o reintentos duplicados.

### Estados

Estados minimos:

- `recorded`: gasto real vigente; reduce el resultado.
- `canceled`: gasto anulado; no reduce el resultado, pero permanece visible.

Transicion unica:

```text
recorded -> canceled
```

Anular exige:

- `expenses.cancel`.
- Motivo obligatorio de longitud razonable.
- Usuario del servidor.
- Fecha/hora UTC del servidor.
- Transaccion y bloqueo de fila.

No existe reactivacion, DELETE ni soft delete. La implementacion aprobada permite editar un gasto `recorded` con `expenses.update`; cada cambio conserva valores anteriores/nuevos en `expense_events`. Un gasto `canceled` no puede modificarse.

### Numeracion

La implementacion usa `expense_number` unico e inmutable con formato `GA-000001`, derivado del ID dentro de la misma transaccion. El numero es interno, no fiscal y puede tener saltos.

## 5. Categorias

Categorias base propuestas:

- Nomina.
- Alimentacion.
- Transporte.
- Materiales e implementos.
- Servicios publicos.
- Mantenimiento.
- Alquiler.
- Otros.

Recomendacion: categorias configurables.

- Crear las categorias base mediante seeder idempotente en 6A.
- Nombre y slug unicos.
- Solo categorias activas aparecen en nuevos gastos y plantillas.
- Una categoria utilizada no se elimina ni cambia de identidad historica; solo se desactiva.
- Desactivar no altera gastos anteriores ni reportes historicos.
- La categoria `Nomina` debe tener una clave de sistema estable, por ejemplo `system_key = payroll`, para no depender de un nombre editable al crear pagos salariales.
- Renombrar una categoria cambia su etiqueta actual. Si se requiere preservar el nombre historico exacto, `expenses` debe guardar tambien `category_name_snapshot`; se recomienda incluir este snapshot desde 6A.
- No permitir desactivar `Nomina` mientras exista configuracion salarial activa u obligaciones que puedan pagarse.

## 6. Comprobantes de gastos

Reutilizar el patron seguro de comprobantes de transferencia con un disco/directorio privado propio.

Contrato recomendado:

- Archivo opcional.
- Formatos: JPG/JPEG, PNG, WEBP o PDF.
- Tamano maximo inicial: 5 MB.
- Verificar MIME real y extension permitida en servidor.
- Nombre fisico aleatorio; nunca usar el nombre entregado por el cliente.
- Particionar por ano/mes Honduras solo para organizacion, no para autorizacion.
- Guardar nombre original normalizado, MIME, tamano, ruta privada, uploader y fecha de carga.
- No devolver `attachment_path` en Resources.
- Servir mediante ruta autenticada y autorizada con `Content-Disposition`, `X-Content-Type-Options: nosniff` y `Cache-Control: private, no-store`.
- Exigir `expenses.view_attachment`, acceso al gasto y, para comprobante salarial, tambien `payroll.view`.
- No crear URL publica, symlink ni acceso directo a `storage`.
- Si la transaccion falla, eliminar el archivo recien almacenado.
- Definir prueba de archivos huerfanos y estrategia de backup/retencion antes de produccion.
- PDF se sirve inline cuando el navegador lo soporte; nunca se interpreta o ejecuta en servidor.

No se recomienda una tabla polimorfica generica en 6A: metadatos directamente en `expenses` son suficientes para un comprobante opcional por gasto. Extraer una abstraccion solo cuando exista un segundo caso real con las mismas reglas.

## 7. Modelo conceptual

6A creo migraciones aditivas y reversibles. Todos los timestamps tecnicos se almacenan en UTC y las fechas operativas se interpretan en `America/Tegucigalpa`.

### `expense_categories`

- `id`.
- `name`.
- `slug` unico.
- `system_key` queda reservado para 6C/6D; no se agrego en 6A porque la nomina permanece fuera de alcance.
- `is_active`.
- `created_by` nullable si se requiere auditoria de configuracion.
- `updated_by` nullable.
- timestamps.

Integridad:

- No soft deletes.
- No eliminar una categoria referenciada.
- Desactivacion en lugar de borrado.
- Indices por `is_active` y nombre/slug.

### `expenses`

- `id`.
- `expense_number` unico e inmutable.
- `checkout_token` UUID unico para idempotencia.
- `category_id`.
- `category_name_snapshot`.
- `expense_date` tipo date.
- `description`.
- `amount decimal(12,2)`.
- `payment_method` controlado: `cash|card|transfer`.
- `vendor` nullable.
- `employee_id` nullable.
- `status`: `recorded|canceled`.
- `notes` nullable.
- `attachment_path` nullable y privado.
- `attachment_original_name` nullable.
- `attachment_mime` nullable.
- `attachment_size` nullable.
- `attachment_uploaded_by` nullable.
- `attachment_uploaded_at` nullable.
- `recorded_by`.
- `canceled_at` nullable.
- `canceled_by` nullable.
- `cancellation_reason` nullable.
- timestamps.

Integridad:

- Monto positivo; nunca `float`.
- FKs de usuarios con `restrictOnDelete` o nullable solo donde conservar historia lo exija. La aplicacion no elimina usuarios.
- Indices sugeridos: `(status, expense_date)`, `(category_id, expense_date)`, `(employee_id, expense_date)`, `(recorded_by, created_at)`, `payment_method` dentro del rango solo si `EXPLAIN` lo justifica.
- Campos de cancelacion todos nulos en `recorded` y completos en `canceled` por regla de dominio.
- Un gasto creado por nomina puede enlazarse desde una unica obligacion.
- No soft deletes.

### `employee_compensation_profiles`

Entidad separada de `users` para conservar vigencia e historial.

- `id`.
- `user_id`.
- `monthly_salary decimal(12,2)`.
- `payment_frequency`: inicialmente `semimonthly`.
- `first_payment_day`: inicialmente 15.
- `second_payment_rule`: inicialmente `last_day_of_month`.
- `effective_from`.
- `effective_to` nullable.
- `is_active`.
- `notes` nullable.
- `configured_by`.
- timestamps.

Reglas:

- Maximo un perfil activo/vigente por empleado y fecha.
- Un cambio de salario cierra el perfil anterior y crea otro; no sobrescribe historia.
- Salario mayor que cero.
- El usuario puede estar desactivado despues; sus perfiles, obligaciones y pagos historicos permanecen.
- Solo usuarios elegibles como empleados pueden recibir configuracion. Owner/administrator requieren decision expresa si tambien cobran salario.
- No guardar datos bancarios, fiscales, identidad nacional, deducciones legales o beneficios en el MVP.

### `payroll_obligations`

Recomendacion: una sola tabla de obligaciones con estados `pending|paid|canceled`, no una tabla separada de pagos.

Campos conceptuales:

- `id`.
- `employee_id`.
- `compensation_profile_id`.
- `period_year`.
- `period_month`.
- `installment_number`: 1 o 2.
- `period_start`.
- `period_end`.
- `due_date`.
- `monthly_salary_snapshot decimal(12,2)`.
- `amount_due decimal(12,2)`.
- `status`: `pending|paid|canceled`.
- `expense_id` nullable y unico.
- `paid_at` nullable.
- `paid_by` nullable.
- `canceled_at` nullable.
- `canceled_by` nullable.
- `cancellation_reason` nullable.
- timestamps.

Restriccion unica:

```text
(employee_id, period_year, period_month, installment_number)
```

Ventajas de una tabla:

- La obligacion conserva su monto y fecha prevista.
- El estado pagado enlaza al gasto real sin duplicar un segundo ledger salarial.
- La restriccion unica evita dos cuotas para el mismo empleado/periodo.
- Cancelar una obligacion excepcional no borra historia.
- El gasto sigue siendo la unica fuente de egreso real en Ganancias.

No se recomienda guardar solo `payroll_payments`: se perderian vencimientos pendientes. Tampoco se recomienda duplicar monto/metodo/comprobante en dos tablas; esos datos reales viven en el gasto enlazado y la obligacion conserva snapshots de lo debido.

## 8. Regla salarial quincenal

Regla inicial propuesta:

- Salario mensual.
- Dos cuotas por mes.
- Primera fecha prevista: dia 15.
- Segunda fecha prevista: ultimo dia calendario del mes.
- Primera cuota: 50% del salario mensual.
- Segunda cuota: salario mensual menos la primera cuota, para absorber cualquier centavo de redondeo.

Ejemplo:

```text
Salario mensual: L 10,001.01
Cuota 1: L 5,000.50
Cuota 2: L 5,000.51
```

Febrero usa 28 o 29 segun el ano. No se fija el dia 30 ni se calcula con la zona horaria del navegador.

### Generacion de obligaciones

- Crear obligaciones idempotentemente para perfiles vigentes, con snapshots del perfil en el momento de generar.
- Una tarea programada puede generar las cuotas proximas, pero la pantalla debe ejecutar una comprobacion idempotente para recuperar periodos no generados si el scheduler fallo.
- Generar una obligacion no crea gasto ni reduce el resultado.
- No marcar automaticamente como pagada al llegar la fecha.
- El pago exige accion humana `Marcar salario como pagado`.
- La notificacion de vencimiento se deduplica por obligacion y tipo de aviso.

### Perfil efectivo a mitad de mes

Para evitar prorrateos inventados en el MVP:

- Al configurar, exigir una `effective_from` y mostrar cual sera la primera cuota afectada.
- Una cuota ya generada conserva su snapshot y no cambia automaticamente.
- Si el salario cambia antes de generar una cuota, se usa el perfil vigente en la fecha prevista de esa cuota.
- Si se necesita cambiar una obligacion ya generada, debe cancelarse con motivo y regenerarse de forma autorizada antes de pagar.
- No prorratear dias trabajados automaticamente.

Queda como decision pendiente definir si un perfil iniciado despues del dia 1 cobra la siguiente cuota completa, se alinea al siguiente mes o acepta un monto inicial manual. Hasta aprobarlo, la UI debe obligar a elegir una primera cuota futura inequívoca y no calcular retroactivos.

## 9. Obligacion pendiente frente a pago realizado

### Obligacion pendiente

- Representa lo que se espera pagar.
- Tiene empleado, periodo, cuota, fecha prevista y monto snapshot.
- Puede estar futura, proxima o vencida.
- No reduce ingresos ni resultado disponible.
- Es visible solo con `payroll.view`.

### Pago realizado

La accion `Marcar salario como pagado` debe:

1. Autorizar `payroll.mark_paid` y `expenses.create`.
2. Bloquear la obligacion con `lockForUpdate`.
3. Confirmar que sigue `pending`.
4. Validar fecha real, metodo y comprobante opcional.
5. Crear un gasto `recorded` con categoria de sistema `Nomina`.
6. Asignar empleado, periodo en descripcion/metadata y actor del servidor.
7. Enlazar `payroll_obligations.expense_id`.
8. Cambiar la obligacion a `paid`, con actor y hora.
9. Publicar notificacion deduplicada dentro de la misma transaccion.
10. Confirmar todo o revertir todo.

Un doble clic o dos usuarios concurrentes deben producir un solo gasto. La restriccion unica de cuota y el `expense_id` unico complementan el bloqueo.

Si posteriormente se anula el gasto salarial, no se debe dejar la obligacion silenciosamente como pagada. La misma accion transaccional debe volverla a `pending` o pasarla a un estado de revision definido. Para el MVP se recomienda `pending` con evento/nota de reversion y conservacion del gasto canceled; esta conducta requiere aprobacion antes de 6D.

## 10. Gastos recurrentes y plantillas

Para comida, Uber, materiales y otros conceptos variables se recomiendan plantillas rapidas, no gastos automaticos.

Una plantilla puede precargar:

- Nombre.
- Categoria.
- Descripcion.
- Monto habitual opcional.
- Metodo habitual opcional.
- Proveedor/destinatario opcional.
- Empleado opcional.

El usuario revisa fecha, monto y ocurrencia y confirma cada gasto real. No crear automaticamente porque el gasto puede no ocurrir o variar.

La recurrencia automatica, aprobaciones multinivel y cuentas por pagar quedan para una fase posterior.

## 11. Integracion con Ganancias Generales

Periodos:

- Hoy.
- Semana.
- Mes.
- Personalizado.

Se debe reutilizar `ReportPeriod` y la semantica Honduras existente, sin confiar en el navegador.

### Tarjetas

- Ingresos brutos.
- Comision POS.
- Ingreso neto.
- Gastos pagados.
- Resultado disponible.
- Nomina pendiente.

`Nomina pendiente` se calcula por `due_date` dentro del periodo seleccionado, o se rotula claramente como pendiente al cierre del periodo. No mezclar una vista de flujo (gastos pagados por `expense_date`) con un saldo global sin indicar el corte.

### Gastos por categoria

- Categoria snapshot.
- Cantidad de gastos `recorded`.
- Total.
- Orden descendente por total.
- Excluir canceled.

### Gastos por dia

- Solo dias con al menos un gasto `recorded`.
- Fecha Honduras.
- Cantidad.
- Total.
- La suma debe reconciliar exactamente con Gastos pagados.

### Nomina pagada por empleado

- Empleado.
- Pagos realizados.
- Total pagado.
- Solo gastos salariales `recorded` enlazados a obligaciones pagadas.
- No inferir nomina por texto libre o solamente por categoria.

### Comparacion mensual

- Mes.
- Ingreso neto operativo.
- Gastos pagados.
- Resultado disponible.
- Sin graficas complejas en la primera fase.

El filtro de empleado requiere dos contratos visibles:

- En rendimiento de ventas: ejecutor del servicio.
- En gastos/nomina: empleado relacionado o pagado.

No usar un unico filtro ambiguo para ocultar que las dimensiones son diferentes. Si se conserva un solo selector global, la UI debe explicar que afecta ambas secciones bajo esas semanticas y las consultas deben probarlo.

## 12. Filtros del modulo Gastos

- Desde.
- Hasta.
- Categoria.
- Estado.
- Metodo de pago.
- Empleado relacionado.
- Registrado por.
- Busqueda por numero, descripcion, proveedor o destinatario.

Reglas:

- Query string estable y filtros combinables.
- Rango inclusivo Honduras convertido de forma consistente.
- Limite inicial de 366 dias para personalizados, salvo necesidad aprobada.
- Paginacion backend, recomendada de 20 registros.
- Orden por `expense_date` descendente, luego ID descendente.
- Categorias desactivadas siguen disponibles como opcion al consultar historico.
- Usuarios desactivados siguen disponibles cuando existen resultados historicos.
- No cargar adjuntos en el listado.

## 13. Permisos y privacidad

Permisos propuestos:

- `expenses.access`: mostrar/entrar al modulo.
- `expenses.view`: listar y ver detalle de gastos autorizados.
- `expenses.create`: registrar gasto.
- `expenses.update`: editar un gasto `recorded` con evento append-only obligatorio.
- `expenses.cancel`: anular con motivo.
- `expenses.view_attachment`: ver comprobante privado no salarial.
- `expenses.manage_categories`: crear, renombrar, activar y desactivar categorias.
- `payroll.view`: ver obligaciones, pagos y comprobantes salariales.
- `payroll.configure`: configurar perfiles salariales.
- `payroll.mark_paid`: confirmar pago de una obligacion.
- `reports.expenses.view`: ver resumen de gastos/resultado en Ganancias.

Matriz recomendada por defecto:

| Permiso | Owner | Administrator | Employee |
|---|---:|---:|---:|
| `expenses.access` | Si | Si | No |
| `expenses.view` | Si | Si | No |
| `expenses.create` | Si | Si | No |
| `expenses.update` | Si | Si | No |
| `expenses.cancel` | Si | Si | No |
| `expenses.view_attachment` | Si | Si para gastos operativos | No |
| `expenses.manage_categories` | Si | Opcional explicito | No |
| `payroll.view` | Si | No | No |
| `payroll.configure` | Si | No | No |
| `payroll.mark_paid` | Si | No | No |
| `reports.expenses.view` | Si | Si | No |

Reglas:

- Administrator puede operar gastos solo segun permisos concedidos.
- Ver gastos operativos no concede ver nomina.
- Un comprobante asociado a nomina exige `payroll.view`, aunque el usuario tenga `expenses.view_attachment`.
- Employee no tiene acceso por defecto a Gastos ni Nomina y nunca puede ver salarios de otros empleados.
- El propio empleado tampoco recibe automaticamente acceso a su salario en el MVP; un portal personal requiere alcance y decisiones de privacidad separados.
- Backend aplica permisos y scopes en rutas, Requests, acciones, consultas y streaming. Ocultar menu o botones no autoriza.
- Los Resources de gastos operativos deben ocultar indicadores salariales a usuarios sin `payroll.view` o excluir por completo esos registros de su consulta.
- Owner debe recibir los permisos persistidos ademas de su bypass para que la navegacion no quede invisible.

## 14. Notificaciones

Eventos propuestos:

- Gasto registrado.
- Gasto anulado.
- Salario proximo a vencer.
- Salario marcado como pagado.
- Salario vencido sin pagar.

Destinatarios:

- Gasto operativo: owner y administrators activos con `notifications.access` y `expenses.view`.
- Nomina: solo usuarios activos con `notifications.access` y `payroll.view`.

Reglas:

- No correo ni WhatsApp.
- No notificar visualizaciones, filtros o descargas.
- Dedupe por destinatario, entidad y tipo de hecho.
- Aviso proximo sugerido: una vez, tres dias antes; debe quedar configurable antes de 6D.
- Aviso vencido: una vez al vencer y, si se desea repetir, definir frecuencia/limite expresamente para no generar spam.
- La campana salarial no debe revelar monto en el texto previo a abrir si existe riesgo de pantalla compartida. La ruta de detalle vuelve a autorizar.
- Anular gasto salarial notifica la reversion a los mismos destinatarios autorizados.

## 15. Matriz de pantallas

| Pantalla | Ruta conceptual | Permiso | Objetivo | Campos / informacion | Acciones | Vacio / errores | Responsive | Fase |
|---|---|---|---|---|---|---|---|---|
| Listado de Gastos | `/expenses` | `expenses.access`, `expenses.view` | Consultar egresos con filtros y paginacion | Numero, fecha, categoria, descripcion, monto, metodo, empleado, estado, registrado por | Filtrar, restablecer, abrir detalle, nuevo gasto si autorizado | Sin gastos; filtros invalidos; 403; error de carga | Tabla desktop, cards movil, filtros colapsables sin scroll horizontal | 6A |
| Nuevo gasto | `/expenses/create` o dialogo desde listado | `expenses.create` | Registrar un egreso real | Fecha, categoria, descripcion, monto, metodo, proveedor, empleado, nota, archivo | Guardar, cancelar | Doble clic, fecha futura, monto invalido, categoria inactiva, archivo invalido, fallo transaccional | Formulario de dos columnas desktop, una columna movil, CTA accesible | 6A |
| Detalle de gasto | `/expenses/{expense}` | `expenses.view` y scope salarial | Auditar registro y comprobante | Todos los snapshots, actor/fecha, estado y anulacion | Ver comprobante; anular si autorizado | 403/404; archivo faltante; canceled visible | Secciones/cards; acciones apiladas movil | 6A |
| Categorias | `/configuration/expense-categories` | `expenses.manage_categories`, `settings.access` | Configurar catalogo | Nombre, estado, uso, clave reservada no editable | Crear, renombrar, activar, desactivar | No permitir eliminar usada ni desactivar Nomina cuando bloquee pagos | Tabla/cards y dialogos fullscreen movil | 6A |
| Compensacion de usuario | `/configuration/users/{user}/compensation` | `payroll.configure`, `settings.access`, `users.view` | Configurar perfil salarial separado | Salario mensual, frecuencia, dias/regla, vigencia, notas, historial | Crear perfil, cerrar/cambiar perfil | Usuario no elegible/inactivo; traslape; fecha ambigua; 403 | Seccion dentro de Usuario, historial en cards movil | 6C |
| Nomina pendiente | `/expenses/payroll` | `payroll.view`, `expenses.access` | Consultar cuotas futuras, proximas y vencidas | Empleado, periodo, cuota, vencimiento, monto, estado | Filtrar, abrir, marcar pagado | Sin perfiles/obligaciones; generacion pendiente; 403 | Tabla desktop, cards por vencimiento movil | 6D |
| Confirmar pago salarial | Dialogo o `/expenses/payroll/{obligation}/pay` | `payroll.mark_paid`, `expenses.create` | Convertir obligacion en gasto real | Empleado/periodo bloqueados, fecha real, monto debido, metodo, comprobante, nota | Confirmar, cancelar | Ya pagado, click duplicado, monto cambiado, archivo invalido, categoria Nomina inactiva | Dialogo desktop, fullscreen movil | 6D |
| Resumen en Ganancias | `/earnings` | `reports.sales.view` y `reports.expenses.view`; nomina requiere `payroll.view` | Mostrar resultado financiero sin CRUD | Tarjetas, categorias, dias, nomina pagada, comparacion mensual, advertencia | Cambiar periodo/filtros, ir a Gastos si autorizado | Mes sin gastos, datos parciales, permiso salarial ausente | Grid de cards, tablas desktop y listas movil | 6B/6D |
| Plantillas rapidas | `/expenses/templates` o seccion del formulario | Permiso futuro de plantillas o `expenses.create` segun decision | Precargar gastos frecuentes | Nombre, categoria, descripcion, monto/metodo habituales | Crear, usar, desactivar | Plantilla inactiva; categoria desactivada; siempre exige confirmacion | Chips/cards tactiles y formulario normal | 6E |

## 16. Integridad y casos limite

1. **Fecha pasada:** permitida; conservar `expense_date` y `created_at` diferentes y mostrar registro posterior.
2. **Fecha futura:** rechazada para gasto pagado. Permitida como `due_date` de obligacion.
3. **Monto cero o negativo:** rechazado en gasto, perfil y obligacion.
4. **Categoria desactivada:** no disponible para nuevos gastos; historicos permanecen. Nomina reservada no se desactiva si rompe pagos pendientes.
5. **Anulacion:** unica, transaccional, con motivo/actor/hora; no borra ni reactiva.
6. **Doble clic:** token idempotente, boton bloqueado e indice unico. Pago salarial usa ademas bloqueo y enlace unico.
7. **Comprobante invalido:** rechazar por MIME/tamano; no persistir gasto parcial; limpiar archivo si falla DB.
8. **Empleado desactivado:** puede aparecer en historicos y obligaciones previas; no crear perfil nuevo. Una obligacion pendiente requiere resolucion, no borrado automatico.
9. **Cambio de salario:** cerrar perfil anterior y crear nuevo; obligaciones generadas conservan snapshot.
10. **Pago del dia 15:** primera cuota vence el dia 15 local, sin depender de hora UTC o navegador.
11. **Ultimo dia de febrero:** calcular calendario real, 28 o 29.
12. **Salario configurado a mitad de mes:** no prorratear automaticamente; exigir primera cuota futura explicita hasta aprobar otra regla.
13. **Pago duplicado:** unico por empleado/ano/mes/cuota, lock y `expense_id` unico.
14. **Cambio de monto despues de generar obligacion:** no mutar silenciosamente; cancelar/regenerar con motivo antes del pago. Perfil nuevo no altera snapshot.
15. **Empleado desactivado con salario pendiente:** conservar obligacion y permitir a un autorizado pagarla o cancelarla con motivo; no generar nuevas cuotas posteriores a la fecha efectiva de cierre laboral que se defina.
16. **Mes sin gastos:** Gastos pagados y Resultado usan cero correctamente; mostrar estado vacio, no filas simuladas.
17. **Gastos anulados:** visibles al filtrar/historial; excluidos de todas las formulas reales.
18. **Timezone Honduras:** `expense_date` es fecha local; timestamps UTC; vencimientos se comparan por fecha Honduras.
19. **Filtros mensuales:** `YYYY-MM`, primer dia incluido y primer dia del mes siguiente excluido para timestamps; date se compara como calendario local.
20. **Permisos y privacidad:** gastos salariales no aparecen, no cuentan por empleado visible ni filtran metadatos a quien carezca `payroll.view`. Totales agregados de Gastos en Ganancias requieren decidir si pueden revelar indirectamente nomina; recomendacion inicial: owner solamente para resumen completo.

Casos adicionales:

- Fallo al publicar notificacion: si se considera parte de la auditoria critica, revierte la transaccion; mantener el patron transaccional actual.
- Archivo privado faltante por incidente: el gasto sigue existiendo; mostrar error auditable sin revelar ruta.
- Dos categorias con igual nombre/slug: restriccion unica y mensaje claro.
- Cambio de nombre de usuario: gasto conserva ID y muestra nombre actual; para auditoria estricta puede agregarse snapshot de nombre, decision pendiente.
- Anular gasto de nomina: coordinar estado de obligacion en la misma transaccion; nunca descontar dos veces.
- Pago tardio: gasto usa fecha real; obligacion conserva fecha prevista. Reportes por gasto y vencimiento pueden caer en periodos distintos y deben rotularlo.

## 17. Fases pequenas

### 6A - Gastos manuales

**Estado:** En pruebas. **Aprobacion:** No.

**Alcance:** categorias configurables; categorias base idempotentes; registro manual; listado paginado; filtros; detalle; edicion auditada; anulacion; comprobante privado; notificaciones; permisos operativos; navegacion `Gastos`.

**Fuera de alcance:** Ganancias, perfiles salariales, obligaciones, plantillas, recurrencia, cash movements, proveedores complejos, impuestos y edicion financiera.

**Migraciones implementadas:** `2026_07_27_100000_create_expense_categories_table.php`, `2026_07_27_100100_create_expenses_table.php` y `2026_07_27_100200_create_expense_events_table.php`. Son aditivas, reversibles y no modifican migraciones historicas.

**Permisos:** `expenses.access`, `expenses.view`, `expenses.create`, `expenses.update`, `expenses.cancel`, `expenses.view_attachment`, `expenses.manage_categories`. Owner recibe todos; administrator todos salvo `manage_categories`; employee ninguno.

**Pruebas automaticas:** roles/403; validacion; dinero; pasado/futuro; categoria activa/inactiva; seeder idempotente; numero/token; doble envio; almacenamiento privado; MIME/tamano/PDF; streaming autorizado; rollback/archivo huerfano; anular una vez; filtros/paginacion/timezone; canceled excluido; consultas sin N+1.

**Criterios de aceptacion:** un gasto valido se registra una vez; ningun archivo es publico; ningun gasto se elimina; anulacion conserva historia; employee no accede; filtros y totales del listado reconcilian.

**Pruebas manuales:** registrar cada metodo/categoria; fecha pasada; archivo de cada formato; archivo invalido; doble clic; anular; URL/archivo sin permiso; owner/admin/employee; categorias usadas/desactivadas; 1440x900, 1024x768, 768x1024 y 390x844.

### 6B - Integracion con Ganancias

**Estado:** En pruebas. **Aprobacion:** No.

**Alcance:** Gastos pagados; Resultado disponible; gastos por categoria; gastos por dia; filtros/periodos Honduras; comparacion mensual simple; advertencia financiera.

**Fuera de alcance:** nomina pendiente/pagada, costos de servicio, impuestos, graficas complejas, exportacion y contabilidad fiscal.

**Migraciones:** ninguna adicional. 6B usa los indices de 6A y no crea tablas precalculadas.

**Permisos:** `reports.expenses.view`; conservar `reports.sales.view` como permiso separado. Definir que ambos se requieren para ver resultado combinado.

**Pruebas automaticas:** formulas en centavos; ventas/gastos canceled; today/week/month/custom; limites Honduras; mes sin gastos; categorias/dias; conciliacion de sumas; permiso parcial; filtros de empleado con semantica explicita; no doble conteo por joins.

**Criterios de aceptacion:** tarjetas reconcilian con ventas y gastos; Resultado no incluye obligaciones; labels no dicen ganancia neta; advertencia visible; usuario sin permiso no recibe props sensibles.

**Pruebas manuales:** periodo con efectivo/tarjeta, gasto registrado/anulado, categorias, dias, mes vacio, rangos y roles; comparar manualmente formula.

### 6C - Configuracion salarial

**Estado:** En pruebas. **Aprobacion:** No.

**Alcance:** perfil separado por usuario; salario mensual; frecuencia quincenal; dia 15/ultimo dia; vigencia e historial; privacidad; configuracion dentro de Usuarios -> Compensacion.

**Fuera de alcance:** generar obligaciones, pagar, bonos, deducciones, anticipos, pago semanal, datos bancarios, contratos, asistencia y calculo laboral.

**Migraciones implementadas:** `employee_compensation_profiles` y `2026_07_27_110300_create_payroll_events_table.php`. `payroll_events` es append-only, conserva entidad, tipo, actor, UTC, valores relevantes y nota; no admite edición ni eliminación.

**Permisos:** `payroll.view`, `payroll.configure`; owner por defecto, administrator/employee no.

**Pruebas automaticas:** autorizacion; privacidad de props; monto exacto; perfiles solapados; cambio sin alterar anterior; usuario inactivo; 50/50 con centavo; febrero bisiesto; vigencia; owner permissions persistidos; evento creado/cierre append-only.

**Criterios de aceptacion:** salario no vive en `users`; historial no se sobrescribe; solo autorizado ve/configura; UI explica primera cuota afectada.

**Pruebas manuales:** configurar, cambiar salario futuro, revisar historial, intentar como admin/employee, usuario activo/inactivo, montos pares/impares y responsive.

### 6D - Obligaciones y pago de nomina

**Estado:** En pruebas. **Aprobacion:** No.

**Alcance:** obligaciones idempotentes; proximas/vencidas; marcar pagado; crear gasto Nomina transaccional; evitar duplicados; comprobante; notificaciones internas; nomina pagada/pendiente en Ganancias.

**Fuera de alcance:** nomina laboral completa, calculo por asistencia, bonos, deducciones, anticipos, horas extra, impuestos, pago bancario automatico y recibo legal de salario.

**Migraciones implementadas:** `payroll_obligations`, enlace unico a `expenses`, indices por estado/vencimiento/empleado y restriccion unica de cuota; auditoria en `payroll_events`.

**Permisos:** `payroll.view`, `payroll.mark_paid`, `expenses.create`, `expenses.view_attachment` mas control salarial. Ninguna asignacion salarial automatica a administrator.

**Pruebas automaticas:** generacion idempotente; 15/ultimo dia; febrero; perfil vigente; snapshot; vencimiento; doble pago concurrente; rollback; gasto/enlace/estado atomicos; canceled; archivo privado; notificaciones y dedupe; employee desactivado; pago tardio; formulas sin pending; privacidad indirecta.

**Criterios de aceptacion:** llegar a una fecha no crea gasto; pago humano crea exactamente un gasto; pending no reduce resultado; paid si; ningun usuario no autorizado ve salario.

**Pruebas manuales:** generar cuotas controladas; pagar una; doble navegador; archivo; pago tardio; anular gasto salarial; empleado inactivo; campana; Ganancias antes/despues; roles y responsive.

### 6E - Plantillas y refinamientos

**Estado:** En pruebas. **Aprobacion:** No.

**Alcance:** plantillas de Uber, comida y materiales; filtros avanzados; estados guardados de filtros si aportan valor; pulido movil; rendimiento medido.

**Fuera de alcance:** recurrencia automatica, aprobaciones multinivel, compras/inventario, cuentas por pagar completas, exportacion y graficas complejas.

**Migraciones implementadas:** `expense_templates` y `2026_07_27_110400_create_expense_template_events_table.php`; no existe DELETE ni recurrencia automatica.

**Permisos:** `expenses.manage_templates` para administrar; `expenses.create` permite usar plantillas activas sin administrar el catalogo. Owner recibe gestion; administrator y employee no por defecto.

**Pruebas automaticas:** plantilla activa/inactiva; categoria inactiva; precarga no autoritativa; monto editable y revalidado; filtros combinados; paginacion; responsive estructural.

**Criterios de aceptacion:** plantilla nunca registra sola; usuario confirma gasto real; operacion frecuente requiere pocos pasos; no hay datos simulados.

**Pruebas manuales:** usar cada plantilla con/sin cambios; cancelar; categoria desactivada; telefonos estrechos; teclado; lista larga y filtros.

## 18. Orden recomendado

1. Aprobar decisiones EXP-D001 a EXP-D005, EXP-D010 a EXP-D013.
2. Implementar y validar 6A.
3. Aprobar manualmente 6A.
4. Implementar y reconciliar 6B.
5. Resolver reglas salariales pendientes y aprobar EXP-D006 a EXP-D009 y EXP-D014.
6. Implementar privacidad/configuracion 6C.
7. Implementar obligaciones/pagos 6D solo despues de validar perfiles.
8. Implementar 6E despues de observar operacion real.

La implementacion conjunta de 6A y 6B fue autorizada expresamente el 2026-07-27. 6C-6E siguen pendientes y cada fase implementada queda `En pruebas` hasta aprobacion manual del usuario.

## 19. Registro de decisiones

| ID | Tema | Decision propuesta | Consecuencia | Estado |
|---|---|---|---|---|
| EXP-D001 | Navegacion | Modulo `Gastos` separado | Operacion diaria fuera del reporte | Aprobada |
| EXP-D002 | Reporte | Resumen de gastos dentro de Ganancias | Sin CRUD en Ganancias | Aprobada |
| EXP-D003 | Categorias | Categorias configurables con base idempotente y desactivacion | Conserva historico y permite adaptar operacion | Aprobada |
| EXP-D004 | Comprobantes | Archivos privados autorizados, sin URL publica | Requiere streaming y permisos | Aprobada |
| EXP-D005 | Correcciones | Anulacion auditada sin eliminacion | No hay DELETE ni reactivacion | Aprobada |
| EXP-D006 | Salario | Salario mensual dividido 50/50, residuo en segunda cuota | Dos snapshots exactos suman salario | Aprobada |
| EXP-D007 | Fechas salariales | Dia 15 y ultimo dia configurables por perfil | Maneja febrero y excepciones por empleado | Aprobada |
| EXP-D008 | Obligacion | Pending no reduce resultado hasta pagarse | Separa compromiso de flujo real | Aprobada |
| EXP-D009 | Pago salarial | Marcar pagado crea gasto de Nomina enlazado | Una fuente de egreso y auditoria atomica | Aprobada |
| EXP-D010 | Frecuentes | Plantillas rapidas, no recurrencia automatica | Cada gasto requiere confirmacion | Aprobada |
| EXP-D011 | Privacidad | Employee sin acceso a nomina por defecto | Salarios solo para permisos explicitos | Aprobada |
| EXP-D012 | Terminologia | Usar `Resultado disponible` | Evita afirmar utilidad contable/fiscal | Aprobada |
| EXP-D013 | Fiscalidad | No calcular impuestos | Reporte operativo, no estado fiscal | Aprobada |
| EXP-D014 | Notificaciones | Avisos internos de nomina a autorizados | Sin correo/WhatsApp y con dedupe | Aprobada |

### Decisiones adicionales que deben cerrarse antes de 6C/6D

- Monto distinto en cada quincena.
- Bonificaciones.
- Deducciones.
- Anticipos.
- Pago semanal.
- Primera cuota cuando el perfil inicia a mitad de mes.
- Tratamiento exacto al anular un gasto salarial pagado.
- Si owner/administrator pueden ser empleados remunerados.
- Dias de anticipacion y repeticion de avisos vencidos.

Recomendacion MVP: mantener todos fuera de alcance salvo la primera cuota efectiva, que debe resolverse antes de generar obligaciones.

## 20. Riesgos

- Llamar ingreso posterior a POS `ganancia neta` seguiria siendo incorrecto aun despues de Gastos.
- Gastos no registrados hacen que Resultado disponible sea mayor que la realidad.
- Separar `expense_date`, `created_at` y `due_date` es necesario para reportes y auditoria; mezclarlos cambia periodos.
- Un filtro generico de empleado puede confundir cobrador, ejecutor, empleado relacionado y empleado pagado.
- Gastos salariales pueden filtrarse indirectamente mediante totales, categorias, busqueda, notificaciones o adjuntos aunque la fila este oculta.
- La consulta de gastos aplica `Expense::visibleTo()`: un gasto enlazado a `payroll_obligations.expense_id` exige `payroll.view`, incluso en detalle, adjunto, edicion, anulacion y agregados.
- Cambiar perfiles sin snapshots alteraria obligaciones historicas.
- Generar gastos automaticamente por fecha produciria egresos falsos.
- Reintentos concurrentes pueden duplicar pagos sin token, indice y bloqueo.
- PDF e imagenes privadas requieren backups, retencion y tratamiento de archivos huerfanos.
- No existe antivirus ni re-encoding de imagenes; debe evaluarse segun el riesgo real de usuarios autorizados.
- La autorizacion actual esta distribuida entre middleware, Requests, acciones y helpers; Gastos/Nomina debe centralizar scopes para evitar divergencias.
- Notificaciones actuales filtran tambien por rol; los eventos salariales deben exigir permiso efectivo y no confiar solo en rol.
- El bundle frontend ya tiene advertencia de tamano; evitar graficas o dependencias nuevas en el MVP.
- SQLite no siempre estuvo disponible en el entorno historico; concurrencia y restricciones deben validarse tambien en MySQL sin usar `migrate:fresh`.
- El plan previo 2I excluia nomina. Este documento es un modulo nuevo y no cambia el estado de 2I/3E; la relacion entre costos de servicios y Gastos debe revisarse antes de usar la palabra utilidad.

## 21. Proxima intervencion: validacion manual 6A/6B

```text
Lee completamente docs/STUDIO_LEMUS_EXPENSES_PLAN.md y los planes principales. Valida manualmente las Fases 6A y 6B ya implementadas: permisos owner/administrator/employee; alta con efectivo, transferencia y tarjeta; comprobantes validos/invalidos; edicion auditada; anulacion; filtros y pagina 2; categorias; notificaciones; reconciliacion de Ganancias; y responsive en 1440x900, 1024x768, 768x1024 y 390x844. No implementes 6C-6E, no cambies 6A/6B a Aprobada sin confirmacion expresa, no uses migrate:fresh y no despliegues ni ejecutes acciones Git.
```

## 22. Resultado de implementacion 6A/6B (2026-07-27)

- Arquitectura: modulo independiente `Gastos` en `/expenses`, sin conectar con `cash_sessions` ni implementar nomina.
- Datos: `expense_categories`, `expenses` y `expense_events`; numeros `GA-000001`; token/hash idempotente; montos `decimal(12,2)` y sin soft deletes.
- Categorias: ocho categorias base mediante seeder idempotente; se gestionan dentro de Gastos y solo se activan/desactivan.
- Operacion: crear, listar, filtrar, paginar, ver detalle, editar `recorded`, anular una vez y consultar auditoria legible.
- Archivos: JPG/JPEG/PNG/WEBP/PDF de hasta 5 MB en `storage/app/private/expense-receipts`, sin symlink ni ruta fisica expuesta.
- Seguridad: Owner recibe todos los permisos; administrator recibe operacion/reporte salvo categorias; employee ninguno.
- Notificaciones: registro, modificacion y anulacion se publican despues del commit, con dedupe y sin convertir un fallo de notificacion en fallo financiero.
- Ganancias: conserva ventas/bruto/POS/neto y agrega Gastos pagados, Resultado disponible, categorias, dias activos y Metodos de gasto separados de Metodos de cobro.
- Semantica de filtros: periodo aplica a ventas y gastos; empleado/metodo de cobro afectan ventas/proyeccion, no ocultan gastos generales.
- Formula: `Resultado disponible = Ingreso neto operativo - Gastos pagados`; canceled se excluye y no se calculan impuestos u obligaciones.
- Migracion local: las tres migraciones de Gastos quedaron en batch 11. Tambien se aplicaron tres migraciones historicas pendientes de Facturas/transferencias/anulacion, sin `migrate:fresh`.
- Pruebas: PHP 8.3, 295 pruebas y 2,821 aserciones correctas. La ejecucion directa con PHP global fallo por SQLite no cargado; PHP 8.3 de Laragon tenia `pdo_sqlite`/`sqlite3` habilitados.
- Calidad: Composer valido, Pint correcto, typecheck correcto, build correcto y `git diff --check` correcto. Persiste la advertencia no bloqueante del bundle mayor de 500 kB.
- Pruebas manuales pendientes: owner/admin/employee; crear los tres metodos; editar/anular; archivos validos/invalidos; filtros/pagina 2; categorias; notificaciones; reconciliar Ganancias; 1440x900, 1024x768, 768x1024 y 390x844.
- No se implementaron perfiles salariales, obligaciones, pagos quincenales, bonos, deducciones, anticipos, recurrencia, plantillas, inventario, proveedores complejos ni fiscalidad.
- No se ejecuto `migrate:fresh`, no se desplego y no se ejecuto `git add`, commit o push.

## 23. Privacidad salarial y contrato de errores 6C (2026-07-27)

- Causa raiz: la prueba enviaba un `PUT` vacio. `UpdateExpenseRequest` validaba antes del controlador y devolvia 302; al fallar `assertForbidden`, Laravel 13 intentaba enriquecer el error en `TestResponseAssert.php:81` con `$session->get('errors')->all()`, aunque `errors` era un array. El fallo no estaba en `HandleInertiaRequests`, `AppServiceProvider`, Resources ni el renderer Inertia.
- Correccion: `EnsureExpenseIsVisible` ejecuta `Expense::visibleTo()` como middleware antes del Form Request en detalle, adjunto, edicion y anulacion. El controlador conserva la misma comprobacion como defensa adicional.
- Contratos: navegador normal recibe 403 con `Errors/Forbidden` en espanol; solicitud `X-Inertia` recibe 403 Inertia valido con header `X-Inertia`; solicitud `Accept: application/json` recibe JSON 403 y no HTML/Inertia.
- Privacidad: administrador sin `payroll.view` no lista, abre, descarga, edita, anula ni agrega gastos enlazados a nomina; no recibe monto, empleado, descripcion, conteos o totales. Owner autorizado conserva acceso.
- Pruebas: `PayrollAuditPrivacyTest` 6/62; Payroll 6/62; Expense 23/289; Notification 17/148; suite completa 301/2,883. Pint, typecheck, build y `git diff --check` correctos; persiste advertencia no bloqueante por bundle mayor de 500 kB.

## 24. Cierre tecnico 6D/6E (2026-07-27)

- Plantillas: CRUD sin borrado en `/expenses/templates`, permiso `expenses.manage_templates`, cards responsive, dialogo fullscreen movil y eventos append-only de crear, actualizar, activar y desactivar.
- Seeder: Uber/Transporte, Almuerzo/Alimentacion y Compra de materiales/Materiales e implementos; idempotente y sin montos inventados.
- Nuevo gasto: selector opcional precarga categoria, descripcion, monto/metodo cuando existen; confirma antes de reemplazar valores, permite editar, Quitar plantilla no borra valores y ninguna seleccion envia el formulario.
- Obligaciones: cada fila nueva publica despues del commit `Nueva obligacion de nomina`, solo a activos autorizados con `payroll.view`, URL mensual y dedupe `payroll-obligation-generated:{id}`. Dry-run, reintento y rollback no notifican.
- Ganancias: `payroll_paid_by_employee` usa exclusivamente gastos `recorded` enlazados a obligaciones `paid`, periodo por `expense_date`, agrupa sin duplicar y solo se entrega con `payroll.view`. Pending/vencida permanecen separadas y no reducen Resultado disponible.
- Rutas nuevas: GET/POST `/expenses/templates`, PUT `/expenses/templates/{expenseTemplate}` y PATCH `/expenses/templates/{expenseTemplate}/status`.
- Pruebas: ExpenseTemplate 4/41; Payroll 9/125; Earnings 29/414; Notification 17/148; suite completa 308/2,987. Pint, typecheck, build y diff correctos.
- Riesgos/manuales: validar owner sin acceso accidental de admin/employee; crear/editar/activar plantillas; precarga/cancelacion en movil; campana tras cron real; reconciliar dos empleados y periodos; revisar 1440x900, 1024x768, 768x1024 y 390x844. Persiste advertencia de bundle mayor de 500 kB.

## 25. Reestructuracion: nomina automatica dentro de Gastos (2026-07-27)

**Estado:** En pruebas. **Aprobacion:** No. **Despliegue:** No realizado.

- Decision vigente: el salario y contrato se configuran al crear o editar personal operativo. `users` conserva solo identidad/acceso; `employee_compensation_profiles` conserva salario, vigencia e historia.
- Contrato: migracion aditiva `2026_07_27_120000_add_contract_and_automation_to_compensation_profiles.php` agrega inicio/fin contractual, indefinido, metodo habitual y generacion automatica. El backfill usa `effective_from/effective_to`, marca perfiles sin final como indefinidos y deja automatizacion historica desactivada.
- Usuarios: employee exige salario positivo, inicio contractual y metodo cuando automatiza. Crear usuario, rol, perfil y auditoria comparten transaccion. Editar crea un perfil nuevo y cierra el anterior; no sobrescribe salarios historicos.
- Procesamiento: `studio:process-payroll --date=YYYY-MM-DD [--dry-run]` recupera cuotas vencidas del dia 15 y ultimo dia real, crea obligacion y gasto Nomina, usa metodo habitual, enlaza `expense_id`, marca paid, audita y notifica post-commit. La restriccion unica existente evita duplicados.
- Incidencias: migracion `2026_07_27_120100_add_processing_errors_to_payroll_obligations.php` conserva error, instante e intentos. Una configuracion invalida deja obligacion pendiente, no crea gasto parcial y notifica a responsables autorizados.
- Gastos: `/expenses` es la unica superficie operativa con `Todos los gastos` y `Nomina`; muestra resumen, cuotas, incidencias y auditoria. Los gastos automaticos muestran origen `Nomina automatica` y no permiten editar monto, categoria, empleado o anular manualmente.
- Navegacion: se retiro `Nomina` del sidebar y la pagina `Payroll/Index`; `/payroll` redirige autorizadamente a `/expenses?section=payroll`. No existen acciones manuales pay/cancel visibles.
- Plantillas: se retiraron permiso, rutas, controlador, seeder, pagina, selector y pruebas exclusivas. `expense_templates` y `expense_template_events` quedan legacy sin uso; sus tres filas existentes no se borraron.
- Ganancias: se retiraron Nomina pendiente/vencida/pagada por empleado. Los gastos salariales recorded participan una sola vez en Gastos pagados, categoria Nomina, dias, metodos y `Resultado disponible = ingreso neto operativo - todos los gastos registrados`.
- Fechas: historiales de compensacion y nomina usan espanol y `America/Tegucigalpa`, sin ISO, UTC, milisegundos, `Z` ni nombres tecnicos.
- Scheduler: `studio:process-payroll` corre diariamente a las 00:15 de Honduras con `withoutOverlapping`. Hostinger documenta `__RUTA_PHP_83__`, `command -v php` y `php -v`; no se invento una ruta del servidor.
- Demo local: `studio:generate-financial-demo --months=2 --sales=20 --force` creo un employee identificable, salario L 15,000, contrato indefinido, transferencia y automatizacion; 20 ventas mediante acciones reales y cuatro pagos mayo-junio mediante el procesador real. La clave aleatoria se mostro una sola vez y no se documento.
- Datos finales: ventas totales 33 (20 demo), gastos totales 5 (4 salariales demo), metodos demo 7 efectivo/7 tarjeta/6 transferencia, 4 obligaciones demo paid, 8 eventos y 16 filas de notificacion para destinatarios autorizados. Las 3 plantillas legacy permanecen; permiso de plantillas: 0.
- Verificacion automatica final: suite 312 pruebas/2,993 aserciones; Pint, typecheck, build y `git diff --check` correctos. Persiste solo la advertencia no bloqueante por chunk mayor de 500 kB.
- Fuera de alcance: impuestos, IHSS, RAP, prestaciones, horas extra, deducciones, bonificaciones, vacaciones, nomina laboral avanzada y transferencias bancarias automaticas.

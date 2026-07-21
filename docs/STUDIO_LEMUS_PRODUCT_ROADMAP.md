# Studio Lemus: hoja de ruta del producto

Este documento define el crecimiento previsto de la plataforma. No representa funcionalidad implementada y no autoriza a crear módulos antes de aprobar su fase correspondiente.

## Principios de producto

- Mantener una experiencia sencilla para personas no técnicas.
- Incorporar cada módulo solo cuando esté completo, autorizado y probado.
- No mostrar opciones futuras deshabilitadas.
- Conservar autorización backend granular en cada operación.
- Registrar importes monetarios como decimales y conservar snapshots históricos cuando corresponda.
- Diferenciar claramente ventas, ingresos y ganancias.

## Roadmap POS simplificado

Cambio de prioridad aprobado el 2026-07-19. Este orden sustituye las fases historicas de caja y reportes cuando exista contradiccion:

| Fase | Nombre | Estado | Aprobacion |
|---|---|---|---|
| 3A | Venta rapida y recibo | Aprobada | Si |
| 3B | Ganancias Generales e ingresos diarios | En pruebas | No |
| 3B.1 | Pago con tarjeta y comision POS | En pruebas | No |
| 3C | Historial y detalle de ventas | Pendiente | No |
| 3D | Anulacion de ventas | Pendiente | No |
| 3E | Costos, gastos y ganancia real | Pendiente | No |

### Fase 3B - Ganancias Generales e ingresos diarios

La pantalla `Ganancias Generales` muestra exclusivamente ingresos brutos por servicios completados: total vendido, ventas, servicios, promedio por venta, totales por empleado y totales por dia. Debe advertir que todavia no incluye costos ni gastos y nunca presentar estos ingresos como ganancia neta, utilidad o margen.

El cierre diario es un resumen automatico calculado desde `sales` para el dia operativo `America/Tegucigalpa`. No requiere apertura o cierre manual, no utiliza `cash_sessions` y no crea tablas de cierres o movimientos de efectivo.

La Fase 3C conserva para despues el historial y detalle individual. La Fase 3D conserva anulaciones. Costos, gastos y calculos de ganancia real pertenecen exclusivamente a la Fase 3E.

**Implementacion:** `GET /earnings`, permiso `reports.sales.view` asignado inicialmente solo a owner, filtros por periodo/empleado, cuatro indicadores, rendimiento por empleado y cierres diarios automaticos. Usa exclusivamente ventas `completed`, limites Honduras convertidos a UTC y los indices existentes de `sales`; no requirio migraciones. Suite temporal completa: 76 pruebas y 501 aserciones. Build correcto con 1,190 modulos. Las pruebas manuales y responsive permanecen pendientes; por ello 3B esta `En pruebas` y no `Aprobada`.

### Fase 3B.1 - Pago con tarjeta y comision POS

**Estado:** En pruebas. **Aprobacion:** No.

Metodos iniciales: efectivo y tarjeta. El cliente siempre paga el total completo. Para tarjeta, Studio Lemus registra una comision interna fija actual del 4% sobre el total e ingreso neto recibido igual a total menos comision. Los precios, lineas y comprobante no se reducen por la comision.

La venta conserva `payment_method`, tasa, comision e ingreso neto como snapshots. Ganancias Generales separa ingresos brutos, comision POS e ingreso neto, y aclara que este neto todavia no descuenta otros costos o gastos. No se agregan Historial, anulaciones, gastos, costos, exportaciones o cierres manuales.

**Implementacion:** migracion batch 4 con cuatro campos obligatorios y sin defaults; siete ventas anteriores normalizadas como efectivo sin cambiar su total; calculo transaccional en centavos; switch y confirmacion; recibo con metodo pero sin costos internos; reportes globales, por empleado y dia con filtro de metodo. Suite temporal: 88 pruebas y 627 aserciones. Build correcto con 1,190 modulos. Pruebas manuales pendientes; 3B.1 no esta aprobada.

## Fase 2: apertura y cierre de caja

Entidad prevista: `cash_sessions`.

Campos conceptuales: `id`, `opened_by`, `closed_by` nullable, `opened_at`, `closed_at` nullable, `opening_amount`, `expected_cash` nullable, `declared_cash` nullable, `difference` nullable, `status`, `opening_notes` nullable y `closing_notes` nullable.

Funciones previstas:

- Abrir caja.
- Consultar la caja actual.
- Cerrar caja.
- Calcular el efectivo esperado.
- Registrar y justificar diferencias.
- Consultar el historial de cierres.
- Impedir ventas en efectivo sin una caja abierta.

Decisión pendiente: definir si solo podrá existir una caja abierta para todo el negocio o una caja abierta por usuario. Para la operación inicial de un único establecimiento se recomienda una caja abierta por negocio, protegida con una restricción transaccional. Esta decisión debe confirmarse antes de implementar.

## Fase 3: ventas y comprobante

Entidades previstas: `sales`, `sale_items` y `payments`.

Conceptos que deberán conservarse:

- Número único de venta.
- Fecha y hora.
- Usuario que realiza el cobro.
- Sesión de caja relacionada cuando corresponda.
- Servicios vendidos con nombre y precio guardados como snapshot.
- Descuento, subtotal y total.
- Uno o varios métodos de pago.
- Estado de la venta.
- Anulación, usuario responsable y motivo.
- Reimpresión del comprobante.

Métodos de pago iniciales: efectivo, tarjeta, transferencia y pago mixto.

La impresión se diseñará para ticket térmico de 80 mm e incluirá Studio Lemus, número de comprobante, fecha, hora, usuario, servicios, totales y formas de pago.

Hasta disponer de requisitos legales explícitos, el documento se llamará **comprobante de venta** o **recibo interno**. No se implementarán CAI, RTN, SAR, rangos fiscales ni facturación legal por inferencia.

## Fase 4: movimientos y gastos

Entidades previstas: `cash_movements` y `expenses`.

Funciones previstas:

- Registrar entradas manuales de efectivo.
- Registrar salidas manuales.
- Registrar gastos operativos.
- Indicar motivo y usuario responsable.
- Adjuntar evidencia opcional.
- Relacionar cada movimiento con la caja abierta cuando corresponda.

## Fase 5: ingresos y ganancias

Definiciones obligatorias:

- **Ingresos:** total de ventas cobradas.
- **Ganancia bruta:** ventas menos costos estimados de los servicios.
- **Ganancia neta:** ventas menos costos, gastos, devoluciones y otras salidas.

El total de ventas nunca debe presentarse como ganancia. Para calcular ganancias reales será necesario incorporar costo estimado por servicio, gastos, anulaciones, devoluciones y posibles comisiones.

Reportes previstos: ventas de hoy, semana y mes; ganancia bruta y neta; servicios más vendidos; métodos de pago; diferencias de caja y cierres diarios.

## Fase 6: permisos futuros

Los siguientes permisos son sugerencias de arquitectura. No deben crearse hasta implementar el módulo asociado.

```text
cash.view
cash.open
cash.close
cash.view_history
cash.create_movement

sales.access
sales.view
sales.create
sales.cancel
sales.reprint

expenses.view
expenses.create
expenses.update
expenses.delete

reports.view
reports.view_profit
reports.export
```

Cada fase deberá incluir migraciones reversibles, Form Requests, policies o middleware, Resources, pruebas feature y una interfaz responsive terminada antes de continuar.

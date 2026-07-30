# Conciliacion financiera - 29 de julio de 2026

## Alcance auditado

Se revisaron las ventas completadas `SL-000014` a `SL-000017`, sus lineas,
pagos y cargos adicionales, junto con `BuildSalesSummaryAction`,
`DailyCloseReportData` y el PDF de cierre.

## Evidencia

| Venta | Servicios | Cargos adicionales | Descuento | Pago | POS | Total |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| SL-000014 | L 700.00 | Diseno L 350.00 | L 0.00 | Tarjeta L 1,050.00 | L 42.00 | L 1,050.00 |
| SL-000015 | L 1,130.00 | Perla L 20.00 | L 0.00 | Tarjeta L 1,150.00 | L 46.00 | L 1,150.00 |
| SL-000016 | L 1,050.00 | Frances L 50.00 | L 0.00 | Transferencia L 1,100.00 | L 0.00 | L 1,100.00 |
| SL-000017 | L 350.00 | L 0.00 | L 0.00 | Tarjeta L 350.00 | L 14.00 | L 350.00 |
| **Total** | **L 3,230.00** | **L 420.00** | **L 0.00** | **L 3,650.00** | **L 102.00** | **L 3,650.00** |

Las siete lineas de servicio tienen ejecutora: Melany suma L 2,180.00 y
Valery L 1,050.00. Sus importes suman L 3,230.00. La diferencia contra los
ingresos brutos es exactamente L 420.00, que coincide con los tres cargos
adicionales. No proviene de descuentos, servicios sin `performed_by`, ventas
compartidas ni snapshots.

## Causa

El reporte global sumaba `sales.total` (L 3,650.00), mientras que la tabla de
empleadas sumaba solamente `sale_items.line_total` (L 3,230.00). Por ello la
participacion se calculaba contra L 3,650.00: Melany 59.73% y Valery 28.77%,
para un total de 88.50%.

Ademas, la comision POS completa se asignaba a servicios usando el total de la
venta como denominador. La porcion POS de los cargos adicionales terminaba en
la ultima linea de servicio, en vez de permanecer no atribuible.

## Criterio de correccion

Cada venta distribuye primero el descuento proporcionalmente entre servicios y
cargos adicionales. La comision POS se distribuye despues sobre esos importes
finales. Los cargos adicionales no reciben `performed_by` y se presentan como
`Ingresos no atribuibles - cargos adicionales`.

Para este cierre, los servicios atribuibles son L 3,230.00: Melany representa
67.49% y Valery 32.51%, que suman 100.00%. Los cargos adicionales constituyen
L 420.00 de ingreso no atribuible; su POS proporcional es L 14.80 y su neto es
L 405.20.

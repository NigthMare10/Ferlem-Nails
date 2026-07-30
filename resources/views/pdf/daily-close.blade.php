<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe de cierre diario</title>
    <style>
        @page { margin: 100px 36px 48px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #382b33; font-family: "DejaVu Sans", sans-serif; font-size: 8.5px; line-height: 1.4; }
        header { position: fixed; top: -78px; left: 0; right: 0; height: 66px; border-bottom: 1px solid #decfd6; }
        .logo { width: 74px; height: 48px; vertical-align: middle; }
        .brand { display: inline-block; margin-left: 12px; vertical-align: middle; }
        .brand h1 { margin: 0 0 3px; color: #6f2449; font-size: 18px; font-weight: 700; }
        .brand p { margin: 0; color: #75666e; font-size: 8px; }
        .meta { float: right; margin-top: 8px; text-align: right; color: #75666e; }
        h2 { margin: 22px 0 8px; color: #6f2449; font-size: 12px; page-break-after: avoid; }
        h3 { margin: 14px 0 6px; font-size: 9px; page-break-after: avoid; }
        .lead { margin: 0 0 12px; color: #75666e; }
        .kpis { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px 14px; }
        .kpis td { width: 25%; padding: 10px; border-radius: 7px; background: #f5eef1; vertical-align: top; }
        .kpis .result { background: #ead9e1; }
        .label { color: #75666e; font-size: 7px; text-transform: uppercase; letter-spacing: .4px; }
        .amount { display: block; margin-top: 5px; color: #382b33; font-size: 13px; font-weight: 700; }
        .negative { color: #a23838; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 12px; page-break-inside: auto; }
        table.data thead { display: table-header-group; }
        table.data th { padding: 6px 5px; color: #6f2449; background: #f5eef1; border-bottom: 1px solid #decfd6; font-size: 7px; text-align: left; }
        table.data td { padding: 6px 5px; border-bottom: 1px solid #eee5e9; vertical-align: top; overflow-wrap: break-word; }
        table.data .num { text-align: right; white-space: nowrap; }
        .muted { color: #75666e; }
        .empty { padding: 14px; border-radius: 7px; color: #75666e; background: #faf7f8; text-align: center; }
        .confidential { margin-top: 18px; padding-top: 9px; border-top: 1px solid #decfd6; color: #75666e; font-size: 7px; }
        .limit-note { margin: 8px 0 12px; padding: 8px; color: #75666e; background: #faf7f8; }
    </style>
</head>
<body>
<header>
    @if($logo)<img class="logo" src="{{ $logo }}" alt="Studio Lemus">@endif
    <div class="brand"><h1>Informe de cierre diario</h1><p>{{ $business_name }} · {{ $operational_date }}</p></div>
    <div class="meta">Generado: {{ $generated_at }}<br>Responsable: {{ $generated_by }}</div>
</header>

<p class="lead">Resumen consolidado de ventas completadas, servicios, métodos de pago y gastos registrados para la fecha operativa indicada.</p>

<table class="kpis">
    <tr>
        <td><span class="label">Ventas completadas</span><span class="amount">{{ number_format($actual['completed_sales_count']) }}</span></td>
        <td><span class="label">Servicios realizados</span><span class="amount">{{ number_format($actual['performed_services_count']) }}</span></td>
        <td><span class="label">Ingresos brutos</span><span class="amount">L {{ number_format((float) $actual['gross_revenue'], 2) }}</span></td>
        <td><span class="label">Comisión POS</span><span class="amount">L {{ number_format((float) $actual['pos_fee'], 2) }}</span></td>
    </tr>
    <tr>
        <td><span class="label">Ingreso neto</span><span class="amount">L {{ number_format((float) $actual['net_income'], 2) }}</span></td>
        <td><span class="label">Gastos registrados</span><span class="amount">L {{ number_format((float) $actual['paid_expenses'], 2) }}</span></td>
        <td class="result" colspan="2"><span class="label">Resultado disponible</span><span class="amount {{ (float) $actual['available_result'] < 0 ? 'negative' : '' }}">L {{ number_format((float) $actual['available_result'], 2) }}</span></td>
    </tr>
</table>

<h2>Métodos de pago</h2>
<table class="data">
    <thead><tr><th>Método</th><th class="num">Pagos</th><th class="num">Bruto</th><th class="num">Comisión POS</th><th class="num">Neto</th></tr></thead>
    <tbody>
    @foreach($payment_distribution as $method)
        <tr><td>{{ $method['method_label'] }}</td><td class="num">{{ $method['payments_count'] }}</td><td class="num">L {{ number_format((float) $method['amount'], 2) }}</td><td class="num">L {{ number_format((float) $method['card_fee_amount'], 2) }}</td><td class="num">L {{ number_format((float) $method['net_amount'], 2) }}</td></tr>
    @endforeach
    </tbody>
</table>

<h2>Desglose por empleado</h2>
@if(count($employees))
<table class="data">
    <thead><tr><th>Empleado</th><th class="num">Servicios</th><th class="num">Bruto</th><th class="num">POS</th><th class="num">Neto</th><th class="num">Proyección</th><th class="num">Participación</th></tr></thead>
    <tbody>
    @foreach($employees as $employee)
        <tr>
            <td><strong>{{ $employee['name'] }}</strong><br><span class="muted">Comisiones/deducciones: no aplican</span></td>
            <td class="num">{{ $employee['services_count'] }}</td>
            <td class="num">L {{ number_format((float) $employee['total_sold'], 2) }}</td><td class="num">L {{ number_format((float) $employee['card_fee_amount'], 2) }}</td><td class="num">L {{ number_format((float) $employee['net_amount'], 2) }}</td>
            <td class="num">L {{ number_format((float) $employee['projected_income'], 2) }}</td><td class="num">{{ $employee['participation_percentage'] }}%</td>
        </tr>
    @endforeach
    </tbody>
</table>
@else<div class="empty">No hubo servicios atribuidos a empleados en esta fecha.</div>@endif

<h2>Gastos por categoría</h2>
@if(count($expense_categories))
<table class="data"><thead><tr><th>Categoría</th><th class="num">Registros</th><th class="num">Total</th></tr></thead><tbody>
@foreach($expense_categories as $category)<tr><td>{{ $category['category_name'] }}</td><td class="num">{{ $category['expenses_count'] }}</td><td class="num">L {{ number_format((float) $category['total'], 2) }}</td></tr>@endforeach
</tbody></table>
@else<div class="empty">No se registraron gastos para esta fecha.</div>@endif

@if(count($expense_details))
<h3>Detalle de gastos</h3>
@if($details_truncated['expenses'])<p class="limit-note">Se muestran los primeros {{ $details_truncated['limit'] }} gastos. Los totales del resumen incluyen todos los registros del día.</p>@endif
<table class="data"><thead><tr><th>Referencia</th><th>Categoría</th><th>Descripción</th><th>Método</th><th class="num">Monto</th></tr></thead><tbody>
@foreach($expense_details as $expense)<tr><td>{{ $expense['reference'] }}</td><td>{{ $expense['category'] }}</td><td>{{ $expense['description'] }}</td><td>{{ $expense['payment_method'] }}</td><td class="num">L {{ number_format((float) $expense['amount'], 2) }}</td></tr>@endforeach
</tbody></table>
@endif

<h2>Detalle de ventas</h2>
@if(count($sales_details))
@if($details_truncated['sales'])<p class="limit-note">Se muestran las primeras {{ $details_truncated['limit'] }} ventas. Los totales del resumen incluyen todas las ventas del día.</p>@endif
<table class="data">
    <thead><tr><th>Referencia / hora</th><th>Clienta</th><th>Empleado y servicios</th><th>Método</th><th class="num">Bruto</th><th class="num">Comisión</th><th class="num">Neto</th></tr></thead>
    <tbody>
    @foreach($sales_details as $sale)
        <tr><td><strong>{{ $sale['reference'] }}</strong><br><span class="muted">{{ $sale['time'] }}</span></td><td>{{ $sale['client'] }}</td><td><strong>{{ $sale['employees'] ?: 'Sin asignar' }}</strong><br><span class="muted">{{ $sale['services'] }}</span></td><td>{{ $sale['payment_methods'] }}</td><td class="num">L {{ number_format((float) $sale['gross'], 2) }}</td><td class="num">L {{ number_format((float) $sale['fee'], 2) }}</td><td class="num">L {{ number_format((float) $sale['net'], 2) }}</td></tr>
    @endforeach
    </tbody>
</table>
@else<div class="empty">No hubo ventas completadas para esta fecha.</div>@endif

<p class="confidential">Confidencial. Este informe contiene información financiera y operativa de Studio Lemus. Su distribución debe limitarse a personal autorizado.</p>
</body>
</html>

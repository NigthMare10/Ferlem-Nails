<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cierre diario Studio Lemus</title>
</head>
<body style="margin:0;background:#f8f4f6;color:#382b33;font-family:Arial,sans-serif;line-height:1.5">
    <div style="max-width:620px;margin:0 auto;padding:32px 20px">
        <div style="background:#ffffff;border:1px solid #decfd6;border-radius:12px;padding:28px">
            <h1 style="margin:0 0 8px;color:#6f2449;font-size:24px">Cierre diario Studio Lemus</h1>
            <p style="margin:0 0 24px;color:#75666e">Resumen del {{ $report->operational_date->format('d/m/Y') }}. El informe completo se encuentra adjunto en PDF.</p>
            <table role="presentation" style="width:100%;border-collapse:collapse">
                <tbody>
                    <tr><td style="padding:8px 0;border-bottom:1px solid #eee5e9">Ingresos brutos</td><td style="padding:8px 0;border-bottom:1px solid #eee5e9;text-align:right"><strong>L {{ number_format((float) $summary['gross_revenue'], 2) }}</strong></td></tr>
                    <tr><td style="padding:8px 0;border-bottom:1px solid #eee5e9">Comisión POS</td><td style="padding:8px 0;border-bottom:1px solid #eee5e9;text-align:right">L {{ number_format((float) $summary['pos_fee'], 2) }}</td></tr>
                    <tr><td style="padding:8px 0;border-bottom:1px solid #eee5e9">Ingreso neto</td><td style="padding:8px 0;border-bottom:1px solid #eee5e9;text-align:right"><strong>L {{ number_format((float) $summary['net_income'], 2) }}</strong></td></tr>
                    <tr><td style="padding:8px 0;border-bottom:1px solid #eee5e9">Gastos</td><td style="padding:8px 0;border-bottom:1px solid #eee5e9;text-align:right">L {{ number_format((float) $summary['paid_expenses'], 2) }}</td></tr>
                    <tr><td style="padding:8px 0;border-bottom:1px solid #eee5e9">Resultado disponible</td><td style="padding:8px 0;border-bottom:1px solid #eee5e9;text-align:right"><strong>L {{ number_format((float) $summary['available_result'], 2) }}</strong></td></tr>
                    <tr><td style="padding:8px 0">Ventas y servicios realizados</td><td style="padding:8px 0;text-align:right">{{ number_format($summary['completed_sales_count']) }} ventas · {{ number_format($summary['performed_services_count']) }} servicios</td></tr>
                </tbody>
            </table>
            <p style="margin:24px 0 0;color:#75666e;font-size:13px">Este correo contiene información financiera confidencial. No lo reenvíes a personas no autorizadas.</p>
        </div>
    </div>
</body>
</html>

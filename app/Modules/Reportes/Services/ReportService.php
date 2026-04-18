<?php

namespace App\Modules\Reportes\Services;

use App\Modules\Sucursales\Models\Sucursal;
use App\Modules\VentasPOS\Models\Orden;

class ReportService
{
    public function resumenSucursal(Sucursal $sucursal): array
    {
        $base = Orden::query()->where('sucursal_id', $sucursal->id);

        return [
            'ordenes_hoy' => (clone $base)->whereDate('created_at', today())->count(),
            'ventas_hoy' => (clone $base)->whereDate('created_at', today())->sum('total_amount'),
            'ordenes_facturadas' => (clone $base)->where('status', 'facturada')->count(),
            'ticket_promedio' => (int) round((clone $base)->avg('total_amount') ?? 0),
        ];
    }
}

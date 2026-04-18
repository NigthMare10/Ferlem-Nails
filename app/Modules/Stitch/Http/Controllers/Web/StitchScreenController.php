<?php

namespace App\Modules\Stitch\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Facturacion\Models\Factura;
use App\Modules\Shared\Support\Money;
use Inertia\Inertia;
use Inertia\Response;

class StitchScreenController extends Controller
{
    protected string $basePath;

    public function __construct()
    {
        $this->basePath = base_path('references/stitch-export/stitch_sistema_pos_u_as_y_pesta_as');
    }

    public function reportes(): Response
    {
        return Inertia::render('Admin/ReportsAnalytics', [
            'title' => 'Reportes de Ventas Analytics',
            'calendarOptions' => [
                ['label' => 'Abril 2026', 'key' => 'abril-2026'],
                ['label' => 'Mayo 2026', 'key' => 'mayo-2026'],
                ['label' => 'Junio 2026', 'key' => 'junio-2026'],
            ],
            'datasets' => [
                'abril-2026' => [
                    'summary' => [
                        'ingresos' => 'L 42,850',
                        'variacionIngresos' => '+12.5% vs mes anterior',
                        'ticketPromedio' => 'L 148',
                        'variacionTicket' => 'Rendimiento estable',
                        'citas' => '312',
                        'variacionCitas' => '+14 esta semana',
                        'categoriaPrincipal' => 'Pestañas',
                        'variacionCategoria' => '58% del total de reservas',
                    ],
                    'trend' => [78, 58, 30, 40],
                    'previousTrend' => [90, 80, 60, 70],
                     'servicePopularity' => [
                         ['name' => 'Manicura Rusa Prestige', 'value' => 32],
                         ['name' => 'Extensiones de Pestañas Volumen', 'value' => 28],
                         ['name' => 'Cejas Ombré Powder', 'value' => 22],
                         ['name' => 'Balayage Signature', 'value' => 18],
                     ],
                     'staffPerformance' => [
                         ['name' => 'Elena Rodriguez', 'department' => 'Arte de Uñas', 'revenue' => 'L 12,450'],
                         ['name' => 'Julian Voss', 'department' => 'Colorista', 'revenue' => 'L 10,120'],
                         ['name' => 'Sofia Amari', 'department' => 'Pestañas', 'revenue' => 'L 14,800'],
                    ],
                     'retention' => [
                         'value' => '74%',
                         'delta' => '+3%',
                         'note' => 'Tu base de clientas recurrentes sigue creciendo. Considera premiar al 5% con mayor gasto mensual.',
                     ],
                     'insight' => [
                         'title' => 'Pico de Demanda de Fin de Semana',
                         'body' => 'La analítica indica un aumento del 42% en servicios de alto ticket entre viernes por la tarde y domingo por la mañana. Conviene abrir dos estaciones adicionales de pestañas en esos intervalos.',
                     ],
                 ],
                'mayo-2026' => [
                     'summary' => [
                         'ingresos' => 'L 46,120',
                         'variacionIngresos' => '+7.6% vs abril',
                         'ticketPromedio' => 'L 154',
                         'variacionTicket' => 'Sube con pestañas premium',
                        'citas' => '328',
                        'variacionCitas' => '+9 esta semana',
                        'categoriaPrincipal' => 'Uñas',
                        'variacionCategoria' => '51% del total de reservas',
                    ],
                    'trend' => [82, 42, 34, 22],
                    'previousTrend' => [88, 70, 58, 40],
                     'servicePopularity' => [
                         ['name' => 'Pestañas Clásicas', 'value' => 34],
                         ['name' => 'Manicura Rusa Prestige', 'value' => 27],
                         ['name' => 'Hydrafacial', 'value' => 21],
                         ['name' => 'Lifting de Pestañas', 'value' => 18],
                     ],
                     'staffPerformance' => [
                         ['name' => 'Camille Laurent', 'department' => 'Pestañas', 'revenue' => 'L 15,340'],
                         ['name' => 'Julianne Moore', 'department' => 'Uñas', 'revenue' => 'L 11,620'],
                         ['name' => 'Marcus Thorne', 'department' => 'Consultoría', 'revenue' => 'L 9,980'],
                     ],
                     'retention' => [
                         'value' => '77%',
                         'delta' => '+3%',
                        'note' => 'Las clientas que reservaron bundles de mantenimiento regresaron con más frecuencia. Mantener esa estrategia puede elevar el ticket recurrente.',
                    ],
                    'insight' => [
                        'title' => 'Aumento de Servicios Recurrentes',
                        'body' => 'Los packs de mantenimiento tuvieron mejor conversión durante mayo. Aprovecha para reforzar ofertas de retoque y reservas automáticas.',
                    ],
                ],
                'junio-2026' => [
                    'summary' => [
                        'ingresos' => 'L 39,980',
                        'variacionIngresos' => '-4.2% vs mayo',
                        'ticketPromedio' => 'L 139',
                        'variacionTicket' => 'Afectado por descuentos estacionales',
                        'citas' => '289',
                        'variacionCitas' => '-11 esta semana',
                        'categoriaPrincipal' => 'Cejas',
                        'variacionCategoria' => '45% del total de reservas',
                    ],
                    'trend' => [72, 48, 62, 44],
                    'previousTrend' => [88, 66, 74, 58],
                     'servicePopularity' => [
                         ['name' => 'Retoque Volumen Ruso', 'value' => 30],
                         ['name' => 'Retiro Gel', 'value' => 26],
                         ['name' => 'Set Híbrido Completo', 'value' => 24],
                         ['name' => 'Tratamiento Express', 'value' => 20],
                     ],
                     'staffPerformance' => [
                         ['name' => 'Aria Chen', 'department' => 'Cejas', 'revenue' => 'L 8,650'],
                         ['name' => 'Liam Foster', 'department' => 'Dermatología', 'revenue' => 'L 10,540'],
                         ['name' => 'Sienna West', 'department' => 'Color', 'revenue' => 'L 12,110'],
                     ],
                     'retention' => [
                         'value' => '71%',
                         'delta' => '-6%',
                         'note' => 'La tasa de retención cayó por promociones externas y menos reservas recurrentes. Conviene reforzar el seguimiento posterior al servicio.',
                     ],
                     'insight' => [
                         'title' => 'Alerta de Retención',
                         'body' => 'Junio presenta menor retorno en clientas nuevas. Revisa los seguimientos post-servicio y los beneficios de rebooking en recepción.',
                     ],
                 ],
            ],
        ]);
    }

    public function historialFacturas(): Response
    {
        return $this->renderScreen('historial_de_facturas', 'Historial de Facturas');
    }

    public function detalleFacturaDigital(?Factura $factura = null): Response
    {
        return $this->renderScreen('detalle_de_factura_digital', 'Detalle de Factura Digital', $factura);
    }

    public function detalleFacturaPremium(?Factura $factura = null): Response
    {
        return $this->renderScreen('detalle_de_factura_premium', 'Detalle de Factura Premium', $factura);
    }

    public function cierreCaja(): Response
    {
        return $this->renderScreen('cierre_de_caja_diario', 'Cierre de Caja Diario');
    }

    public function listaEmpleados(): Response
    {
        return $this->renderScreen('lista_de_empleados', 'Lista de Empleados');
    }

    public function rendimientoEmpleado(): Response
    {
        return $this->renderScreen('rendimiento_por_empleado', 'Rendimiento por Empleado');
    }

    public function gestionEmpleadosAdmin(): Response
    {
        return Inertia::render('Admin/EmployeeManagement', [
            'title' => 'Gestion de Empleados Admin',
            'employees' => [
                [
                    'id' => 'camille-laurent',
                    'name' => 'Camille Laurent',
                    'role' => 'Tecnica Master de Pestañas',
                    'status' => 'Activo',
                    'statusVariant' => 'active',
                    'specialties' => ['Volumen', 'Clasicas', 'Hibridas'],
                    'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDTboIwz6A2v6TLDBuuryNg0h38xLCEb7-4KaH4GOoz9YuIbHPjK-yYa08v89j5i3JcK06v6VlH_VCIGIi7GA-X7L5WzXsnvIazXQDVeJk2jyOVLWSL7smW3Y3fyGCHhd4CpA0q3EC4nVI3MUz8u3xkHAP3VPARK-HlKwUGx_i_FGUE_MOTUrULp1gXzQhzoS2sFIyy26uXKnKZc6RD64HBCIVSiXPAaipDtQYsoZwBDvCeaQPy76lE9hTa2Lx_c9Vgw5QcwXkUQI7w',
                    'email' => 'camille@ferlemnails.local',
                    'startDate' => '2023-02-10',
                ],
                [
                    'id' => 'julian-vance',
                    'name' => 'Julian Vance',
                    'role' => 'Artista Senior de Uñas',
                    'status' => 'Activo',
                    'statusVariant' => 'active',
                    'specialties' => ['Gel-X', 'Nail Art'],
                    'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAzdSsrvxEwCMTn_GfbDS4qcOyjFcTn95iv3yVsS12QtTOwariuTM5vxdVd42BKlFo8uQIVIXFksD9YbhKzU-r0eglHT_c5azbOhT0-rmMxRJ6BBGU8B1qKD6Qow-IEQPAZ4Jv7NB6VndGkdHI2Ts1C4TeNaNlbnMMsPZxJQQfHFlMqCR1GAlQeosZRpJuBIup4rjUoUo6cob4wIzAn6j7C9agMKY1nlzqv9d1pAOe-Xq7H1o6kDeI3jZbbt3uyO1Rt2BcbstpiAbI_',
                    'email' => 'julian@ferlemnails.local',
                    'startDate' => '2022-11-04',
                ],
                [
                    'id' => 'sienna-brooks',
                    'name' => 'Sienna Brooks',
                    'role' => 'Esteticista',
                    'status' => 'En Pausa',
                    'statusVariant' => 'paused',
                    'specialties' => ['Consultoria', 'Piel'],
                    'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBdt24FkfGKH_WfLiRhl8KzFkyULUCMvxtlLI4AGUtIbzw2c027dzNU9CO0FGYR31Culhr2-jruZWgHdc9Xoxx8NMpKk1eZfitYjCASHIRgl5rRQ_mlKiARfG2t_NAIHz3gBU1690_sLQCc8DeMTBbh3n3743djNn9cRHh3cgUlggOLZudCG4gbCs72bnIVpkXT1K864-c2dOO2qiy2ZCI03BeP9t-58Ef3q1cpdIKCPsUZvNDOzrZ2u0nYyiVL3B61wohsoHbVIAIp',
                    'email' => 'sienna@ferlemnails.local',
                    'startDate' => '2021-08-19',
                ],
            ],
            'specializationOptions' => [
                'Tecnica de Pestañas',
                'Artista de Uñas',
                'Esteticista',
                'Lider de Salon',
            ],
            'summaryCards' => [
                ['value' => '12', 'label' => 'Especialistas Activos'],
                ['value' => '94%', 'label' => 'Tasa de Retencion'],
                ['value' => '240+', 'label' => 'Reservas Semanales'],
            ],
        ]);
    }

    public function ajustePreciosAdmin(): Response
    {
        return $this->renderScreen('ajuste_de_precios_admin', 'Ajuste de Precios Admin');
    }

    protected function renderScreen(string $folder, string $title, ?Factura $factura = null): Response
    {
        $html = file_get_contents($this->basePath.'\\'.$folder.'\\code.html');

        $html = str_replace(
            ['L\'Élégance Salon', 'L\'Élégance', 'L\'Artiste', 'Lustre', 'Lumière Atelier', 'Elegance Suite'],
            ['FERLEM NAILS', 'FERLEM NAILS', 'FERLEM NAILS', 'FERLEM NAILS', 'FERLEM NAILS', 'FERLEM NAILS'],
            $html,
        );

        $html = str_replace(['MXN', 'USD', 'EUR', '$'], ['HNL', 'HNL', 'HNL', 'L '], $html);

        $invoicePayload = $factura ? $this->serializeInvoice($factura) : null;

        if ($factura && $invoicePayload) {
            $html = str_replace(['INV-2024-0001', 'LF-2048'], [$factura->number, $factura->number], $html);
            $html = $this->applyInvoiceDataToHtml($html, $invoicePayload);
        }

        return Inertia::render('Stitch/Frame', [
            'title' => $title,
            'html' => $html,
            'invoice' => $invoicePayload,
        ]);
    }

    protected function serializeInvoice(Factura $factura): array
    {
        $factura->loadMissing(['detalles', 'usuario.empleado']);

        return [
            'public_id' => $factura->public_id,
            'number' => $factura->number,
            'issued_date' => $factura->issued_at?->translatedFormat('d \d\e F, Y'),
            'issued_time' => $factura->issued_at?->format('h:i a'),
            'operator_name' => $factura->usuario?->empleado?->name ?? $factura->usuario?->name ?? 'FERLEM NAILS',
            'subtotal_formatted' => Money::format((int) $factura->subtotal_amount),
            'tax_formatted' => Money::format((int) $factura->tax_amount),
            'total_formatted' => Money::format((int) $factura->total_amount),
            'currency_code' => $factura->currency_code,
            'items' => $factura->detalles->map(fn ($detail) => [
                'description' => $detail->description,
                'quantity' => $detail->quantity,
                'unit_price_formatted' => Money::format((int) $detail->unit_price_amount),
                'subtotal_formatted' => Money::format((int) $detail->subtotal_amount),
                'tax_formatted' => Money::format((int) $detail->tax_amount),
                'total_formatted' => Money::format((int) $detail->total_amount),
            ])->values()->all(),
        ];
    }

    protected function applyInvoiceDataToHtml(string $html, array $invoice): string
    {
        if (empty($invoice['items'])) {
            return $html;
        }

        $itemsHtml = collect($invoice['items'])->map(function (array $item): string {
            return sprintf(
                '<div class="grid grid-cols-12 gap-4 group"><div class="col-span-8"><h4 class="font-headline text-2xl text-on-surface mb-1">%s</h4><p class="text-sm text-on-surface-variant max-w-sm leading-relaxed">Cantidad: %s • Unitario: %s</p></div><div class="col-span-4 text-right self-center"><span class="font-headline text-2xl text-primary">%s</span></div></div>',
                e($item['description']),
                e((string) $item['quantity']),
                e($item['unit_price_formatted']),
                e($item['total_formatted']),
            );
        })->implode('');

        $html = preg_replace(
            '/<!-- Services List \(Asymmetric Editorial Style\) -->[\s\S]*?<!-- Totals Section -->/',
            '<!-- Services List (Asymmetric Editorial Style) --><div class="mb-20 space-y-12"><div class="grid grid-cols-12 gap-4 pb-4 border-b-2 border-on-surface text-xs uppercase tracking-widest font-bold text-on-surface"><div class="col-span-8">Descripción del Servicio</div><div class="col-span-4 text-right">Monto</div></div>'.$itemsHtml.'</div><!-- Totals Section -->',
            $html,
            1,
        ) ?? $html;

        $html = preg_replace('/Factura #INV-240801/', 'Factura #'.$invoice['number'], $html, 1) ?? $html;
        $html = preg_replace('/01 de Agosto, 2024/', $invoice['issued_date'] ?? 'Abril 2026', $html, 1) ?? $html;
        $html = preg_replace('/14:30 PM/', $invoice['issued_time'] ?? '12:00 p. m.', $html, 1) ?? $html;
        $html = preg_replace('/Valeria Santamarina/', $invoice['operator_name'], $html, 1) ?? $html;
        $html = preg_replace('/<span class="font-semibold">L [^<]+<\/span>/', '<span class="font-semibold">'.$invoice['subtotal_formatted'].'</span>', $html, 1) ?? $html;
        $html = preg_replace('/<span class="font-semibold">L [^<]+<\/span>/', '<span class="font-semibold">'.$invoice['tax_formatted'].'</span>', $html, 1) ?? $html;
        $html = preg_replace('/<span class="font-headline text-5xl text-on-surface tracking-tighter">L [^<]+<\/span>/', '<span class="font-headline text-5xl text-on-surface tracking-tighter">'.$invoice['total_formatted'].'</span>', $html, 1) ?? $html;
        $html = str_replace('Moneda: HNL', 'Moneda: '.$invoice['currency_code'], $html);

        return $html;
    }
}

<?php

namespace App\Modules\Stitch\Services;

use App\Modules\Empleados\Models\Empleado;
use App\Modules\Facturacion\Models\DetalleFactura;
use App\Modules\Facturacion\Models\Factura;
use App\Modules\Shared\Support\Money;
use App\Modules\Sucursales\Models\Sucursal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdminMetricsService
{
    protected array $invoiceCache = [];

    protected array $activeEmployeeCache = [];

    protected array $historyRowCache = [];

    public function buildHistoryPayload(Sucursal $branch, array $filters = []): array
    {
        $rows = $this->filteredHistoryRows($branch, $filters)->values();
        $allRows = $this->historyRows($branch);

        return [
            'summary' => [
                'total_invoices_label' => number_format($allRows->count(), 0, '.', ','),
                'total_invoices_count' => $allRows->count(),
                'total_revenue_label' => Money::format((int) $allRows->sum('total_amount')), 
                'average_ticket_label' => Money::format($allRows->count() > 0 ? (int) round($allRows->sum('total_amount') / $allRows->count()) : 0),
                'services_count_label' => number_format((int) $allRows->sum('services_count'), 0, '.', ','),
                'range_label' => $this->historyRangeLabel($allRows),
            ],
            'employees' => $this->activeEmployees($branch)->map(fn (Empleado $employee) => [
                'publicId' => $employee->public_id,
                'name' => $employee->name,
            ])->values()->all(),
            'invoices' => $rows->all(),
            'filters' => [
                'query' => trim((string) ($filters['query'] ?? '')),
                'employeePublicId' => $filters['employee_public_id'] ?? '',
                'status' => $filters['status'] ?? 'all',
            ],
        ];
    }

    public function filteredHistoryRows(Sucursal $branch, array $filters = []): Collection
    {
        $query = Str::lower(trim((string) ($filters['query'] ?? '')));
        $employeePublicId = trim((string) ($filters['employee_public_id'] ?? ''));
        $status = Str::lower(trim((string) ($filters['status'] ?? 'all')));

        return $this->historyRows($branch)->filter(function (array $row) use ($employeePublicId, $query, $status): bool {
            $matchesEmployee = $employeePublicId === ''
                || in_array($employeePublicId, $row['operator_public_ids'], true);

            $matchesStatus = $status === ''
                || $status === 'all'
                || $row['status_key'] === $status;

            $matchesQuery = $query === ''
                || Str::contains(Str::lower($row['search_index']), $query);

            return $matchesEmployee && $matchesStatus && $matchesQuery;
        })->values();
    }

    public function buildAnalyticsPayload(Sucursal $branch): array
    {
        $months = collect(range(0, 2))
            ->map(fn (int $offset) => $this->currentMonth($branch)->subMonths($offset)->startOfMonth())
            ->values();

        $calendarOptions = $months->map(fn (CarbonImmutable $month) => [
            'label' => Str::title($month->locale('es')->translatedFormat('F Y')),
            'key' => $month->format('Y-m'),
        ])->all();

        $datasets = [];

        foreach ($months as $month) {
            $key = $month->format('Y-m');
            $currentRows = $this->detailAssignments($branch, $month->startOfMonth(), $month->endOfMonth());
            $currentInvoices = $this->historyRowsForWindow($branch, $month->startOfMonth(), $month->endOfMonth());
            $previousMonth = $month->subMonth()->startOfMonth();
            $previousRows = $this->detailAssignments($branch, $previousMonth->startOfMonth(), $previousMonth->endOfMonth());
            $previousInvoices = $this->historyRowsForWindow($branch, $previousMonth->startOfMonth(), $previousMonth->endOfMonth());

            $currentRevenue = (int) $currentInvoices->sum('total_amount');
            $previousRevenue = (int) $previousInvoices->sum('total_amount');
            $currentServices = (int) $currentRows->sum('quantity');
            $previousServices = (int) $previousRows->sum('quantity');
            $currentTicket = $currentInvoices->count() > 0 ? (int) round($currentRevenue / $currentInvoices->count()) : 0;
            $previousTicket = $previousInvoices->count() > 0 ? (int) round($previousRevenue / $previousInvoices->count()) : 0;
            $topCategory = $this->topCategoryEntry($currentRows);
            $topService = $this->topServiceEntry($currentRows);
            $topEmployee = $this->topEmployeeEntry($branch, $currentRows);

            $datasets[$key] = [
                'summary' => [
                    'ingresos' => Money::format($currentRevenue),
                    'variacionIngresos' => $this->formatDeltaText($currentRevenue, $previousRevenue, 'vs mes anterior'),
                    'ticketPromedio' => Money::format($currentTicket),
                    'variacionTicket' => $this->formatDeltaText($currentTicket, $previousTicket, 'ticket vs mes anterior'),
                    'servicios' => number_format($currentServices, 0, '.', ','),
                    'variacionServicios' => $this->formatDeltaText($currentServices, $previousServices, 'vs mes anterior'),
                    'categoriaPrincipal' => $topCategory['name'],
                    'variacionCategoria' => $topCategory['note'],
                ],
                'trend' => $this->chartPoints($this->weeklyRevenueTotals($currentInvoices, $month->startOfMonth())),
                'previousTrend' => $this->chartPoints($this->weeklyRevenueTotals($previousInvoices, $previousMonth->startOfMonth())),
                'servicePopularity' => $this->servicePopularityEntries($currentRows),
                'staffPerformance' => $this->staffPerformanceEntries($branch, $currentRows),
                'retention' => [
                    'value' => 'N/D',
                    'delta' => 'Sin datos',
                    'note' => 'La retención no está disponible porque el flujo actual no registra clientas recurrentes de forma consistente en todas las facturas.',
                ],
                'insight' => [
                    'title' => $currentRevenue > 0 ? 'Fuente real del periodo' : 'Sin datos del periodo',
                    'body' => $currentRevenue > 0
                        ? 'Mayor ingreso real: '.$topEmployee['name'].' con '.$topEmployee['revenue'].'. Servicio dominante: '.$topService['name'].'.'
                        : 'No existen facturas reales en este periodo para la sucursal activa.',
                ],
            ];
        }

        return [
            'calendarOptions' => $calendarOptions,
            'datasets' => $datasets,
        ];
    }

    public function buildEmployeePayload(Sucursal $branch, Empleado $employee): array
    {
        $employeeRows = $this->detailAssignments($branch)->filter(function (array $row) use ($employee): bool {
            return in_array($employee->public_id, $row['assigned_employee_public_ids'], true);
        })->values();

        $historyRows = $this->historyRows($branch)->filter(function (array $row) use ($employee): bool {
            return in_array($employee->public_id, $row['operator_public_ids'], true);
        })->values();

        $totalRevenue = (int) $employeeRows->sum('revenue_amount');
        $invoiceCount = $historyRows->count();
        $serviceCount = (int) $employeeRows->sum('quantity');
        $averageTicket = $invoiceCount > 0 ? (int) round($totalRevenue / $invoiceCount) : 0;
        $averageDuration = $serviceCount > 0
            ? (int) round($employeeRows->sum(fn (array $row) => $row['duration_minutes'] * $row['quantity']) / $serviceCount)
            : 0;

        $currentMonth = $this->currentMonth($branch)->startOfMonth();
        $previousMonth = $currentMonth->subMonth()->startOfMonth();

        $currentRevenue = (int) $employeeRows->filter(fn (array $row) => $row['issued_at']->betweenIncluded($currentMonth, $currentMonth->endOfMonth()))->sum('revenue_amount');
        $previousRevenue = (int) $employeeRows->filter(fn (array $row) => $row['issued_at']->betweenIncluded($previousMonth, $previousMonth->endOfMonth()))->sum('revenue_amount');

        return [
            'publicId' => $employee->public_id,
            'name' => $employee->name,
            'role' => $employee->role_title ?: 'Perfil operativo',
            'level' => 'N/D',
            'since' => $employee->hire_date?->locale('es')->translatedFormat('F Y') ? Str::title($employee->hire_date->locale('es')->translatedFormat('F Y')) : 'Sin fecha registrada',
            'supportingLabel' => $invoiceCount > 0
                ? number_format($invoiceCount, 0, '.', ',').' facturas reales'
                : 'Sin facturas reales registradas',
            'metrics' => [
                'totalRevenue' => Money::format($totalRevenue),
                'revenueDelta' => $this->formatDeltaText($currentRevenue, $previousRevenue, 'vs mes anterior'),
                'serviceTime' => $averageDuration > 0 ? $averageDuration.' min' : '0 min',
                'averageTicket' => Money::format($averageTicket),
                'invoiceCount' => number_format($invoiceCount, 0, '.', ','),
                'invoiceCountNote' => 'facturas atendidas',
                'servicesCount' => number_format($serviceCount, 0, '.', ','),
                'servicesCountNote' => 'servicios realizados',
            ],
            'chart' => $this->weeklyChartEntries($employeeRows),
            'specialties' => $this->employeeTopServices($employeeRows),
            'appointments' => $this->employeeRecentEntries($employeeRows),
            'history' => $this->employeeHistoryEntries($employeeRows),
            'earningsBreakdown' => $this->employeeBreakdownEntries($employeeRows),
            'insight' => $totalRevenue > 0
                ? 'Facturación real concentrada en '.$this->topServiceEntry($employeeRows)['name'].' con '.number_format($invoiceCount, 0, '.', ',').' facturas asociadas en la sucursal activa.'
                : 'Este empleado aún no tiene facturas reales asociadas en la sucursal activa.',
        ];
    }

    protected function historyRows(Sucursal $branch): Collection
    {
        if (array_key_exists($branch->id, $this->historyRowCache)) {
            return collect($this->historyRowCache[$branch->id]);
        }

        $rows = $this->branchInvoices($branch)->map(function (Factura $invoice) use ($branch): array {
            $payment = $invoice->orden?->pagos?->sortByDesc('paid_at')->first();
            $reference = $payment?->reference ?: 'REF-'.Str::upper(Str::substr($invoice->public_id, -8));
            $operators = $this->invoiceOperators($invoice);
            $issuedAt = $this->invoiceIssuedAt($invoice, $branch);
            $statusLabel = $this->statusLabel($invoice->status);

            $row = [
                'public_id' => $invoice->public_id,
                'number' => $invoice->number,
                'reference' => $reference,
                'issued_date' => $issuedAt?->locale('es')->translatedFormat('d M Y') ?? 'Sin fecha',
                'issued_date_short' => $issuedAt?->locale('es')->translatedFormat('d M Y') ?? 'Sin fecha',
                'issued_time' => $issuedAt?->format('h:i a') ?? '--:--',
                'issued_timezone' => $this->branchTimezone($branch),
                'issued_at' => $issuedAt,
                'operator_name' => $operators['names']->implode(', '),
                'operator_public_ids' => $operators['public_ids']->values()->all(),
                'status_label' => $statusLabel,
                'status_key' => 'pagada',
                'total_formatted' => Money::format((int) $invoice->total_amount),
                'total_amount' => (int) $invoice->total_amount,
                'total_raw' => number_format(((int) $invoice->total_amount) / 100, 2, '.', ''),
                'detail_url' => route('facturas.show', $invoice, absolute: false),
                'client_name' => $invoice->cliente?->name ?? $invoice->orden?->cliente?->name ?? 'Sin cliente',
                'payment_method' => $this->paymentMethodLabel($payment?->method),
                'services_count' => (int) $invoice->detalles->sum('quantity'),
            ];

            $row['search_index'] = collect([
                $row['number'],
                $row['reference'],
                $row['operator_name'],
                $row['client_name'],
                $row['status_label'],
                $row['total_formatted'],
                $row['total_raw'],
            ])->implode(' ');

            return $row;
        })->values();

        $this->historyRowCache[$branch->id] = $rows->all();

        return $rows;
    }

    protected function detailAssignments(Sucursal $branch, ?CarbonImmutable $start = null, ?CarbonImmutable $end = null): Collection
    {
        return $this->branchInvoices($branch)
            ->filter(function (Factura $invoice) use ($start, $end, $branch): bool {
                $issuedAt = $this->invoiceIssuedAt($invoice, $branch);

                if (! $issuedAt) {
                    return false;
                }

                if ($start && $issuedAt->lt($start)) {
                    return false;
                }

                if ($end && $issuedAt->gt($end)) {
                    return false;
                }

                return true;
            })
            ->flatMap(function (Factura $invoice) use ($branch): Collection {
                $payment = $invoice->orden?->pagos?->sortByDesc('paid_at')->first();
                $fallbackEmployee = $invoice->usuario?->empleado;
                $fallbackPublicId = $fallbackEmployee?->public_id;
                $fallbackName = $fallbackEmployee?->name ?? $invoice->usuario?->name ?? 'Sin asignar';
                $issuedAt = $this->invoiceIssuedAt($invoice, $branch);

                return $invoice->detalles->map(function (DetalleFactura $detail) use ($invoice, $payment, $fallbackEmployee, $fallbackPublicId, $fallbackName, $issuedAt): array {
                    $assignedEmployee = $detail->empleado ?: $fallbackEmployee;
                    $assignedPublicIds = $detail->empleado
                        ? array_values(array_filter([$detail->empleado->public_id]))
                        : array_values(array_filter([$fallbackPublicId]));

                    return [
                        'invoice_public_id' => $invoice->public_id,
                        'invoice_number' => $invoice->number,
                        'issued_at' => $issuedAt,
                        'assigned_employee_public_ids' => $assignedPublicIds,
                        'assigned_employee_name' => $assignedEmployee?->name ?? $fallbackName,
                        'assigned_employee_role' => $assignedEmployee?->role_title ?? $fallbackEmployee?->role_title ?? 'Perfil operativo',
                        'service_name' => $detail->description,
                        'service_category' => $detail->servicio?->categoria?->name ?? 'Sin categoría',
                        'quantity' => (int) $detail->quantity,
                        'duration_minutes' => (int) $detail->duration_minutes,
                        'revenue_amount' => (int) $detail->total_amount,
                        'revenue_formatted' => Money::format((int) $detail->total_amount),
                        'status_label' => $this->statusLabel($invoice->status),
                        'status_key' => 'pagada',
                        'client_name' => $invoice->cliente?->name ?? $invoice->orden?->cliente?->name ?? 'Sin cliente',
                        'payment_method' => $this->paymentMethodLabel($payment?->method),
                    ];
                });
            })->values();
    }

    protected function historyRowsForWindow(Sucursal $branch, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return $this->historyRows($branch)->filter(function (array $row) use ($start, $end): bool {
            return $row['issued_at'] instanceof CarbonImmutable
                && $row['issued_at']->betweenIncluded($start, $end);
        })->values();
    }

    protected function branchInvoices(Sucursal $branch): Collection
    {
        if (array_key_exists($branch->id, $this->invoiceCache)) {
            return $this->invoiceCache[$branch->id];
        }

        return $this->invoiceCache[$branch->id] = Factura::query()
            ->with([
                'cliente',
                'usuario.empleado',
                'orden.cliente',
                'orden.pagos',
                'detalles.servicio.categoria',
                'detalles.empleado',
                'sucursal',
            ])
            ->where('sucursal_id', $branch->id)
            ->latest('issued_at')
            ->get();
    }

    protected function activeEmployees(Sucursal $branch): Collection
    {
        if (array_key_exists($branch->id, $this->activeEmployeeCache)) {
            return $this->activeEmployeeCache[$branch->id];
        }

        return $this->activeEmployeeCache[$branch->id] = Empleado::query()
            ->with('usuario')
            ->where('is_active', true)
            ->whereHas('sucursales', fn ($query) => $query->where('sucursal_id', $branch->id))
            ->orderBy('name')
            ->get();
    }

    protected function invoiceOperators(Factura $invoice): array
    {
        $detailEmployees = $invoice->detalles
            ->map(fn (DetalleFactura $detail) => $detail->empleado)
            ->filter()
            ->unique('id')
            ->values();

        if ($detailEmployees->isNotEmpty()) {
            return [
                'names' => $detailEmployees->pluck('name')->filter()->values(),
                'public_ids' => $detailEmployees->pluck('public_id')->filter()->values(),
            ];
        }

        $fallbackName = $invoice->usuario?->empleado?->name ?? $invoice->usuario?->name ?? 'Sin asignar';
        $fallbackPublicId = $invoice->usuario?->empleado?->public_id;

        return [
            'names' => collect([$fallbackName]),
            'public_ids' => collect(array_filter([$fallbackPublicId])),
        ];
    }

    protected function invoiceIssuedAt(Factura $invoice, Sucursal $branch): ?CarbonImmutable
    {
        $timezone = $this->branchTimezone($branch);

        return $invoice->issued_at
            ? CarbonImmutable::parse($invoice->issued_at)->timezone($timezone)
            : null;
    }

    protected function weeklyRevenueTotals(Collection $historyRows, CarbonImmutable $monthStart): array
    {
        $totals = [0, 0, 0, 0];

        foreach ($historyRows as $row) {
            if (! ($row['issued_at'] instanceof CarbonImmutable)) {
                continue;
            }

            $weekIndex = min(3, max(0, intdiv($monthStart->diffInDays($row['issued_at']), 7)));
            $totals[$weekIndex] += (int) $row['total_amount'];
        }

        return $totals;
    }

    protected function chartPoints(array $totals): array
    {
        $max = max($totals) ?: 1;

        return array_map(function (int $total) use ($max): int {
            if ($total <= 0) {
                return 88;
            }

            return (int) round(88 - (($total / $max) * 52));
        }, $totals);
    }

    protected function servicePopularityEntries(Collection $rows): array
    {
        $totalQuantity = (int) $rows->sum('quantity');

        if ($totalQuantity === 0) {
            return [];
        }

        return $rows
            ->groupBy('service_name')
            ->map(function (Collection $group, string $service) use ($totalQuantity): array {
                $quantity = (int) $group->sum('quantity');

                return [
                    'name' => $service,
                    'value' => max(1, (int) round(($quantity / $totalQuantity) * 100)),
                ];
            })
            ->sortByDesc('value')
            ->take(4)
            ->values()
            ->all();
    }

    protected function staffPerformanceEntries(Sucursal $branch, Collection $rows): array
    {
        $employees = $this->activeEmployees($branch);

        return $employees
            ->map(function (Empleado $employee) use ($rows): array {
                $employeeRows = $rows->filter(fn (array $row) => in_array($employee->public_id, $row['assigned_employee_public_ids'], true));

                return [
                    'employeePublicId' => $employee->public_id,
                    'name' => $employee->name,
                    'department' => $employee->role_title ?: 'Perfil operativo',
                    'revenue' => Money::format((int) $employeeRows->sum('revenue_amount')),
                    'revenue_amount' => (int) $employeeRows->sum('revenue_amount'),
                ];
            })
            ->sortByDesc('revenue_amount')
            ->take(3)
            ->values()
            ->map(fn (array $entry) => [
                'employeePublicId' => $entry['employeePublicId'],
                'name' => $entry['name'],
                'department' => $entry['department'],
                'revenue' => $entry['revenue'],
            ])
            ->all();
    }

    protected function topCategoryEntry(Collection $rows): array
    {
        $totalRevenue = (int) $rows->sum('revenue_amount');

        if ($totalRevenue === 0) {
            return [
                'name' => 'Sin datos',
                'note' => 'Sin facturas reales en el periodo',
            ];
        }

        $entry = $rows
            ->groupBy('service_category')
            ->map(fn (Collection $group, string $category) => [
                'name' => $category,
                'amount' => (int) $group->sum('revenue_amount'),
            ])
            ->sortByDesc('amount')
            ->values()
            ->first();

        $share = $entry ? (int) round(($entry['amount'] / $totalRevenue) * 100) : 0;

        return [
            'name' => $entry['name'] ?? 'Sin datos',
            'note' => $entry ? $share.'% del total facturado' : 'Sin facturas reales en el periodo',
        ];
    }

    protected function topServiceEntry(Collection $rows): array
    {
        $entry = $rows
            ->groupBy('service_name')
            ->map(fn (Collection $group, string $service) => [
                'name' => $service,
                'amount' => (int) $group->sum('revenue_amount'),
            ])
            ->sortByDesc('amount')
            ->values()
            ->first();

        return [
            'name' => $entry['name'] ?? 'Sin datos',
            'amount' => Money::format((int) ($entry['amount'] ?? 0)),
        ];
    }

    protected function topEmployeeEntry(Sucursal $branch, Collection $rows): array
    {
        $entry = collect($this->staffPerformanceEntries($branch, $rows))->first();

        return [
            'name' => $entry['name'] ?? 'Sin datos',
            'revenue' => $entry['revenue'] ?? Money::format(0),
        ];
    }

    protected function weeklyChartEntries(Collection $rows): array
    {
        $now = CarbonImmutable::now();
        $starts = collect(range(3, 0))->map(fn (int $weeksAgo) => $now->subWeeks($weeksAgo)->startOfWeek());

        $totals = $starts->map(function (CarbonImmutable $start) use ($rows): int {
            $end = $start->endOfWeek();

            return (int) $rows->filter(fn (array $row) => $row['issued_at'] instanceof CarbonImmutable && $row['issued_at']->betweenIncluded($start, $end))->sum('revenue_amount');
        })->values();

        $max = max($totals->all()) ?: 1;

        return $starts->map(function (CarbonImmutable $start, int $index) use ($totals, $max): array {
            $value = (int) $totals[$index];

            return [
                'label' => 'Sem '.($index + 1),
                'value' => Money::format($value),
                'height' => $value > 0 ? (int) max(24, round(($value / $max) * 100)) : 24,
            ];
        })->all();
    }

    protected function employeeTopServices(Collection $rows): array
    {
        $totalRevenue = (int) $rows->sum('revenue_amount');

        if ($totalRevenue === 0) {
            return [];
        }

        return $rows
            ->groupBy('service_name')
            ->map(function (Collection $group, string $service) use ($totalRevenue): array {
                $amount = (int) $group->sum('revenue_amount');

                return [
                    'service' => $service,
                    'share' => max(1, (int) round(($amount / $totalRevenue) * 100)),
                ];
            })
            ->sortByDesc('share')
            ->take(3)
            ->values()
            ->all();
    }

    protected function employeeRecentEntries(Collection $rows): array
    {
        return $rows
            ->sortByDesc(fn (array $row) => $row['issued_at']?->timestamp ?? 0)
            ->take(6)
            ->map(fn (array $row) => [
                'date' => $row['issued_at']?->locale('es')->translatedFormat('d M, h:i a') ?? 'Sin fecha',
                'service' => $row['service_name'],
                'client' => $row['client_name'],
                'status' => $row['status_label'],
                'revenue' => $row['revenue_formatted'],
            ])
            ->values()
            ->all();
    }

    protected function employeeHistoryEntries(Collection $rows): array
    {
        return $rows
            ->sortByDesc(fn (array $row) => $row['issued_at']?->timestamp ?? 0)
            ->map(fn (array $row) => [
                'folio' => $row['invoice_number'],
                'date' => $row['issued_at']?->locale('es')->translatedFormat('d M Y, h:i a') ?? 'Sin fecha',
                'service' => $row['service_name'],
                'client' => $row['client_name'],
                'status' => $row['status_label'],
                'revenue' => $row['revenue_formatted'],
                'paymentMethod' => $row['payment_method'],
            ])
            ->values()
            ->all();
    }

    protected function employeeBreakdownEntries(Collection $rows): array
    {
        $entries = $rows
            ->groupBy('service_category')
            ->map(fn (Collection $group, string $category) => [
                'label' => $category,
                'value' => Money::format((int) $group->sum('revenue_amount')),
                'amount' => (int) $group->sum('revenue_amount'),
            ])
            ->sortByDesc('amount')
            ->take(3)
            ->values()
            ->map(fn (array $entry) => [
                'label' => $entry['label'],
                'value' => $entry['value'],
            ])
            ->all();

        return $entries === []
            ? [['label' => 'Sin datos', 'value' => Money::format(0)]]
            : $entries;
    }

    protected function currentMonth(Sucursal $branch): CarbonImmutable
    {
        return CarbonImmutable::now($this->branchTimezone($branch));
    }

    protected function branchTimezone(Sucursal $branch): string
    {
        return $branch->timezone ?: config('app.timezone', 'America/Tegucigalpa');
    }

    protected function statusLabel(?string $status): string
    {
        return match (Str::lower((string) $status)) {
            'emitida', 'facturada', 'pagada', 'aplicado' => 'Pagada',
            'cancelada', 'cancelado' => 'Cancelada',
            default => Str::title((string) $status ?: 'Sin estado'),
        };
    }

    protected function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            'efectivo' => 'Efectivo',
            'tarjeta_manual' => 'Tarjeta manual',
            'transferencia' => 'Transferencia',
            default => 'No registrado',
        };
    }

    protected function historyRangeLabel(Collection $rows): string
    {
        if ($rows->isEmpty()) {
            return 'Sin registros';
        }

        $latest = $rows->first();
        $oldest = $rows->last();

        return ($oldest['issued_date_short'] ?? 'Sin fecha').' - '.($latest['issued_date_short'] ?? 'Sin fecha');
    }

    protected function formatDeltaText(int|float $current, int|float $previous, string $suffix): string
    {
        if ($current === 0 && $previous === 0) {
            return 'Sin movimiento real';
        }

        if ($previous === 0) {
            return $current > 0 ? '+100% '.$suffix : 'Sin comparativo real';
        }

        $delta = (($current - $previous) / $previous) * 100;
        $prefix = $delta >= 0 ? '+' : '';

        return $prefix.number_format($delta, 1, '.', '').'% '.$suffix;
    }
}

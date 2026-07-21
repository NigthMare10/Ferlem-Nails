<?php

namespace App\Actions\Reports;

use App\Models\Sale;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

final class BuildSalesSummaryAction
{
    public const TIMEZONE = 'America/Tegucigalpa';

    public function execute(array $filters): array
    {
        $period = $filters['period'] ?? 'today';
        [$localStart, $localEnd, $referenceDate] = $this->periodBounds($period, $filters);
        $baseQuery = $this->baseQuery(
            $localStart,
            $localEnd,
            $filters['employee_id'] ?? null,
            $filters['payment_method'] ?? null,
        );

        $totals = (clone $baseQuery)
            ->toBase()
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(sales.total_services), 0) as services_count')
            ->selectRaw('COALESCE(SUM(sales.total), 0) as total_sold')
            ->selectRaw('COALESCE(SUM(sales.card_fee_amount), 0) as card_fee_amount')
            ->selectRaw('COALESCE(SUM(sales.net_amount), 0) as net_amount')
            ->first();

        $totalCents = $this->toCents($totals->total_sold);
        $salesCount = (int) $totals->sales_count;

        $employees = (clone $baseQuery)
            ->join('users', 'users.id', '=', 'sales.sold_by')
            ->select(['sales.sold_by', 'users.name'])
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(sales.total_services), 0) as services_count')
            ->selectRaw('COALESCE(SUM(sales.total), 0) as total_sold')
            ->selectRaw('COALESCE(SUM(sales.card_fee_amount), 0) as card_fee_amount')
            ->selectRaw('COALESCE(SUM(sales.net_amount), 0) as net_amount')
            ->groupBy('sales.sold_by', 'users.name')
            ->orderByRaw('SUM(sales.total) DESC')
            ->get()
            ->map(function ($row) {
                $employeeTotalCents = $this->toCents($row->total_sold);
                $employeeSalesCount = (int) $row->sales_count;

                return [
                    'id' => (int) $row->sold_by,
                    'name' => $row->name,
                    'sales_count' => $employeeSalesCount,
                    'services_count' => (int) $row->services_count,
                    'total_sold' => $this->formatCents($employeeTotalCents),
                    'card_fee_amount' => $this->formatCents($this->toCents($row->card_fee_amount)),
                    'net_amount' => $this->formatCents($this->toCents($row->net_amount)),
                    'average_sale' => $this->formatCents($this->averageCents($employeeTotalCents, $employeeSalesCount)),
                ];
            })
            ->values()
            ->all();

        $daily = $this->dailySummary($baseQuery);

        return [
            'filters' => [
                'period' => $period,
                'date' => $period === 'custom' ? null : $referenceDate->format('Y-m-d'),
                'date_from' => $period === 'custom' ? $localStart->format('Y-m-d') : null,
                'date_to' => $period === 'custom' ? $localEnd->subDay()->format('Y-m-d') : null,
                'employee_id' => isset($filters['employee_id']) ? (int) $filters['employee_id'] : null,
                'payment_method' => $filters['payment_method'] ?? null,
            ],
            'period' => [
                'label' => $this->periodLabel($period, $localStart, $localEnd),
                'start_date' => $localStart->format('Y-m-d'),
                'end_date' => $localEnd->subDay()->format('Y-m-d'),
                'timezone' => self::TIMEZONE,
                'week_starts_on' => 'monday',
            ],
            'summary' => [
                'total_sold' => $this->formatCents($totalCents),
                'card_fee_amount' => $this->formatCents($this->toCents($totals->card_fee_amount)),
                'net_amount' => $this->formatCents($this->toCents($totals->net_amount)),
                'sales_count' => $salesCount,
                'services_count' => (int) $totals->services_count,
                'average_sale' => $this->formatCents($this->averageCents($totalCents, $salesCount)),
            ],
            'employees' => $employees,
            'daily' => $daily,
        ];
    }

    private function periodBounds(string $period, array $filters): array
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $reference = isset($filters['date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $filters['date'], self::TIMEZONE)->startOfDay()
            : $today;

        return match ($period) {
            'today' => [$reference, $reference->addDay(), $reference],
            'week' => [
                $reference->startOfWeek(CarbonInterface::MONDAY),
                $reference->startOfWeek(CarbonInterface::MONDAY)->addWeek(),
                $reference,
            ],
            'month' => [$reference->startOfMonth(), $reference->startOfMonth()->addMonth(), $reference],
            'custom' => $this->customBounds($filters, $today),
            default => throw new RuntimeException('Periodo de reporte no soportado.'),
        };
    }

    private function customBounds(array $filters, CarbonImmutable $fallback): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $filters['date_from'], self::TIMEZONE)->startOfDay();
        $end = CarbonImmutable::createFromFormat('Y-m-d', $filters['date_to'], self::TIMEZONE)->startOfDay()->addDay();

        return [$start, $end, $fallback];
    }

    private function baseQuery(
        CarbonImmutable $localStart,
        CarbonImmutable $localEnd,
        mixed $employeeId,
        mixed $paymentMethod,
    ): Builder {
        return Sale::query()
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->where('sales.sold_at', '>=', $localStart->utc())
            ->where('sales.sold_at', '<', $localEnd->utc())
            ->when($employeeId !== null, fn (Builder $query) => $query->where('sales.sold_by', (int) $employeeId))
            ->when($paymentMethod !== null, fn (Builder $query) => $query->where('sales.payment_method', $paymentMethod));
    }

    private function dailySummary(Builder $baseQuery): array
    {
        $days = [];

        foreach ((clone $baseQuery)->get([
            'sales.sold_at',
            'sales.total',
            'sales.total_services',
            'sales.card_fee_amount',
            'sales.net_amount',
        ]) as $sale) {
            $date = $sale->sold_at->setTimezone(self::TIMEZONE)->format('Y-m-d');
            $days[$date] ??= [
                'sales_count' => 0,
                'services_count' => 0,
                'total_cents' => 0,
                'card_fee_cents' => 0,
                'net_amount_cents' => 0,
            ];
            $days[$date]['sales_count']++;
            $days[$date]['services_count'] += $sale->total_services;
            $days[$date]['total_cents'] += $this->toCents($sale->total);
            $days[$date]['card_fee_cents'] += $this->toCents($sale->card_fee_amount);
            $days[$date]['net_amount_cents'] += $this->toCents($sale->net_amount);
        }

        krsort($days);

        return collect($days)->map(function (array $values, string $date) {
            $localDate = CarbonImmutable::createFromFormat('Y-m-d', $date, self::TIMEZONE)->locale('es');

            return [
                'date' => $date,
                'date_label' => $localDate->translatedFormat('j \\d\\e F \\d\\e Y'),
                'sales_count' => $values['sales_count'],
                'services_count' => $values['services_count'],
                'total_sold' => $this->formatCents($values['total_cents']),
                'card_fee_amount' => $this->formatCents($values['card_fee_cents']),
                'net_amount' => $this->formatCents($values['net_amount_cents']),
            ];
        })->values()->all();
    }

    private function periodLabel(string $period, CarbonImmutable $start, CarbonImmutable $end): string
    {
        $inclusiveEnd = $end->subDay();

        return match ($period) {
            'today' => $start->locale('es')->translatedFormat('j \\d\\e F \\d\\e Y'),
            'month' => ucfirst($start->locale('es')->translatedFormat('F \\d\\e Y')),
            default => $start->format('d/m/Y').' al '.$inclusiveEnd->format('d/m/Y'),
        };
    }

    private function toCents(mixed $amount): int
    {
        $value = trim((string) ($amount ?? '0'));

        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw new RuntimeException('La base de datos devolvió un importe no válido.');
        }

        $fraction = str_pad($matches[2] ?? '', 3, '0');
        $cents = ((int) $matches[1] * 100) + (int) substr($fraction, 0, 2);

        if ((int) $fraction[2] >= 5) {
            $cents++;
        }

        return $cents;
    }

    private function averageCents(int $totalCents, int $salesCount): int
    {
        if ($salesCount === 0) {
            return 0;
        }

        return intdiv($totalCents + intdiv($salesCount, 2), $salesCount);
    }

    private function formatCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}

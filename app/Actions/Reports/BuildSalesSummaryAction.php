<?php

namespace App\Actions\Reports;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Money;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class BuildSalesSummaryAction
{
    public const TIMEZONE = ReportPeriod::TIMEZONE;

    public function execute(array $filters): array
    {
        $period = $filters['period'] ?? 'today';
        [$localStart, $localEnd, $referenceDate] = ReportPeriod::bounds($filters);
        $employeeId = isset($filters['employee_id']) ? (int) $filters['employee_id'] : null;
        $paymentMethod = $filters['payment_method'] ?? null;
        $sales = $this->salesQuery($localStart, $localEnd, $paymentMethod);
        $items = $this->itemsQuery($localStart, $localEnd, $paymentMethod)
            ->when($employeeId !== null, fn (Builder $query) => $query->where('sale_items.performed_by', $employeeId));

        if ($employeeId === null) {
            $totals = (clone $sales)->toBase()
                ->selectRaw('COUNT(*) as sales_count')
                ->selectRaw('COALESCE(SUM(sales.total_services), 0) as services_count')
                ->selectRaw('COALESCE(SUM(sales.total), 0) as total_sold')
                ->selectRaw('COALESCE(SUM(sales.card_fee_amount), 0) as card_fee_amount')
                ->selectRaw('COALESCE(SUM(sales.net_amount), 0) as net_amount')
                ->first();
        } else {
            $totals = (clone $items)->toBase()
                ->selectRaw('COUNT(DISTINCT sale_items.sale_id) as sales_count')
                ->selectRaw('COALESCE(SUM(sale_items.quantity), 0) as services_count')
                ->selectRaw('COALESCE(SUM(sale_items.line_total), 0) as total_sold')
                ->selectRaw('COALESCE(SUM(sale_items.allocated_card_fee_amount), 0) as card_fee_amount')
                ->selectRaw('COALESCE(SUM(sale_items.net_line_amount), 0) as net_amount')
                ->first();
        }

        $totalCents = Money::toCents((string) $totals->total_sold);
        $salesCount = (int) $totals->sales_count;
        $summary = [
            'total_sold' => Money::fromCents($totalCents),
            'card_fee_amount' => Money::fromCents(Money::toCents((string) $totals->card_fee_amount)),
            'net_amount' => Money::fromCents(Money::toCents((string) $totals->net_amount)),
            'sales_count' => $salesCount,
            'services_count' => (int) $totals->services_count,
            'average_sale' => Money::fromCents($this->averageCents($totalCents, $salesCount)),
        ];

        $employees = (clone $items)
            ->join('users', 'users.id', '=', 'sale_items.performed_by')
            ->select(['sale_items.performed_by', 'users.name'])
            ->selectRaw('COUNT(DISTINCT sale_items.sale_id) as sales_count')
            ->selectRaw('COALESCE(SUM(sale_items.quantity), 0) as services_count')
            ->selectRaw('COALESCE(SUM(sale_items.line_total), 0) as total_sold')
            ->selectRaw('COALESCE(SUM(sale_items.allocated_card_fee_amount), 0) as card_fee_amount')
            ->selectRaw('COALESCE(SUM(sale_items.net_line_amount), 0) as net_amount')
            ->groupBy('sale_items.performed_by', 'users.name')
            ->orderByRaw('SUM(sale_items.line_total) DESC')
            ->get()
            ->map(function ($row) {
                $totalCents = Money::toCents((string) $row->total_sold);
                $salesCount = (int) $row->sales_count;

                return [
                    'id' => (int) $row->performed_by,
                    'name' => $row->name,
                    'sales_count' => $salesCount,
                    'services_count' => (int) $row->services_count,
                    'total_sold' => Money::fromCents($totalCents),
                    'card_fee_amount' => Money::fromCents(Money::toCents((string) $row->card_fee_amount)),
                    'net_amount' => Money::fromCents(Money::toCents((string) $row->net_amount)),
                    'average_sale' => Money::fromCents($this->averageCents($totalCents, $salesCount)),
                ];
            })
            ->values()
            ->all();

        $daily = $this->dailySummary($employeeId === null ? $sales : $items, $employeeId !== null);

        return [
            'filters' => [
                'period' => $period,
                'mode' => $filters['mode'] ?? 'actual',
                'date' => $period === 'custom' ? null : $referenceDate->format('Y-m-d'),
                'date_from' => $period === 'custom' ? $localStart->format('Y-m-d') : null,
                'date_to' => $period === 'custom' ? $localEnd->subDay()->format('Y-m-d') : null,
                'employee_id' => $employeeId,
                'payment_method' => $paymentMethod,
            ],
            'period' => [
                'label' => ReportPeriod::label($period, $localStart, $localEnd),
                'start_date' => $localStart->format('Y-m-d'),
                'end_date' => $localEnd->subDay()->format('Y-m-d'),
                'timezone' => self::TIMEZONE,
                'week_starts_on' => 'monday',
            ],
            'actual' => [
                'gross_revenue' => $summary['total_sold'],
                'pos_fee' => $summary['card_fee_amount'],
                'net_income' => $summary['net_amount'],
                'completed_sales_count' => $summary['sales_count'],
                'performed_services_count' => $summary['services_count'],
                'average_sale' => $summary['average_sale'],
            ],
            'summary' => $summary,
            'employees' => $employees,
            'daily' => $daily,
        ];
    }

    private function salesQuery(CarbonImmutable $start, CarbonImmutable $end, mixed $paymentMethod): Builder
    {
        return Sale::query()
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->where('sales.sold_at', '>=', $start->utc())
            ->where('sales.sold_at', '<', $end->utc())
            ->when($paymentMethod !== null, fn (Builder $query) => $query->whereHas(
                'payments', fn (Builder $payments) => $payments->where('method', $paymentMethod),
            ));
    }

    private function itemsQuery(CarbonImmutable $start, CarbonImmutable $end, mixed $paymentMethod): Builder
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->where('sales.sold_at', '>=', $start->utc())
            ->where('sales.sold_at', '<', $end->utc())
            ->when($paymentMethod !== null, fn (Builder $query) => $query->whereHas(
                'sale.payments', fn (Builder $payments) => $payments->where('method', $paymentMethod),
            ));
    }

    private function dailySummary(Builder $query, bool $usesItems): array
    {
        $days = [];
        $columns = $usesItems
            ? ['sales.sold_at', 'sale_items.sale_id', 'sale_items.quantity', 'sale_items.line_total', 'sale_items.allocated_card_fee_amount', 'sale_items.net_line_amount']
            : ['sales.id', 'sales.sold_at', 'sales.total', 'sales.total_services', 'sales.card_fee_amount', 'sales.net_amount'];

        foreach ((clone $query)->get($columns) as $row) {
            $date = CarbonImmutable::parse((string) $row->sold_at, 'UTC')->setTimezone(self::TIMEZONE)->format('Y-m-d');
            $days[$date] ??= ['sale_ids' => [], 'services_count' => 0, 'total_cents' => 0, 'card_fee_cents' => 0, 'net_cents' => 0];
            $days[$date]['sale_ids'][(int) ($usesItems ? $row->sale_id : $row->id)] = true;
            $days[$date]['services_count'] += (int) ($usesItems ? $row->quantity : $row->total_services);
            $days[$date]['total_cents'] += Money::toCents((string) ($usesItems ? $row->line_total : $row->total));
            $days[$date]['card_fee_cents'] += Money::toCents((string) ($usesItems ? $row->allocated_card_fee_amount : $row->card_fee_amount));
            $days[$date]['net_cents'] += Money::toCents((string) ($usesItems ? $row->net_line_amount : $row->net_amount));
        }
        krsort($days);

        return collect($days)->map(function (array $values, string $date) {
            $localDate = CarbonImmutable::createFromFormat('Y-m-d', $date, self::TIMEZONE)->locale('es');

            return [
                'date' => $date,
                'date_label' => $localDate->translatedFormat('j \\d\\e F \\d\\e Y'),
                'sales_count' => count($values['sale_ids']),
                'services_count' => $values['services_count'],
                'total_sold' => Money::fromCents($values['total_cents']),
                'card_fee_amount' => Money::fromCents($values['card_fee_cents']),
                'net_amount' => Money::fromCents($values['net_cents']),
            ];
        })->values()->all();
    }

    private function averageCents(int $totalCents, int $salesCount): int
    {
        return $salesCount === 0 ? 0 : intdiv($totalCents + intdiv($salesCount, 2), $salesCount);
    }
}

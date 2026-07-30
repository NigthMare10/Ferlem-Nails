<?php

namespace App\Actions\Reports;

use App\Models\Sale;
use App\Support\Money;
use App\Support\ReportPeriod;
use App\Support\SaleFinancials;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class BuildSalesSummaryAction
{
    public const TIMEZONE = ReportPeriod::TIMEZONE;

    private const CHUNK_SIZE = 500;

    public function execute(array $filters): array
    {
        $period = $filters['period'] ?? 'today';
        [$localStart, $localEnd, $referenceDate] = ReportPeriod::bounds($filters);
        $employeeId = isset($filters['employee_id']) ? (int) $filters['employee_id'] : null;
        $paymentMethod = $filters['payment_method'] ?? null;
        $totals = $this->emptyTotals();
        $employees = [];
        $daily = [];
        $methods = $this->emptyMethods();

        $sales = $this->salesQuery($localStart, $localEnd, $paymentMethod)
            ->with(['items.performedBy:id,name', 'additionalCharges.performedBy:id,name', 'payments']);

        foreach ($sales->lazyById(self::CHUNK_SIZE, 'sales.id', 'id') as $sale) {
            $allocation = $this->allocation($sale);
            $date = $sale->sold_at->setTimezone(self::TIMEZONE)->format('Y-m-d');

            if ($employeeId === null) {
                $daily[$date] ??= $this->emptyTotals();
                $daily[$date]['sale_ids'][$sale->id] = true;
                $this->addGlobalSale($totals, $sale, $allocation);
                $this->addGlobalSale($daily[$date], $sale, $allocation);
            }

            foreach ($sale->items as $index => $item) {
                if ($employeeId !== null && $item->performed_by !== $employeeId) {
                    continue;
                }
                if (! $item->performed_by || ! $item->performedBy) {
                    continue;
                }

                $performerId = $item->performed_by;
                $employees[$performerId] ??= $this->emptyEmployee($performerId, $item->performedBy->name);
                $this->addService($employees[$performerId], $sale, $item, $allocation['lines'][$index]);

                if ($employeeId !== null) {
                    $daily[$date] ??= $this->emptyTotals();
                    $daily[$date]['sale_ids'][$sale->id] = true;
                    $this->addService($totals, $sale, $item, $allocation['lines'][$index]);
                    $this->addService($daily[$date], $sale, $item, $allocation['lines'][$index]);
                }
            }

            foreach ($allocation['charges'] as $charge) {
                foreach ($charge as $assigned) {
                    if ($employeeId !== null && $assigned['performer_id'] !== $employeeId) {
                        continue;
                    }

                    $performerId = $assigned['performer_id'];
                    $employees[$performerId] ??= $this->emptyEmployee($performerId, $assigned['performer_name']);
                    $this->addCharge($employees[$performerId], $assigned);

                    if ($employeeId !== null) {
                        $daily[$date] ??= $this->emptyTotals();
                        $daily[$date]['sale_ids'][$sale->id] = true;
                        $this->addCharge($totals, $assigned);
                        $this->addCharge($daily[$date], $assigned);
                    }
                }
            }

            if ($employeeId === null) {
                $this->addPayments($methods, $sale, $paymentMethod);
            }
        }

        $summary = $this->formatTotals($totals);
        $employeeRows = collect($employees)
            ->map(fn (array $employee) => $this->formatEmployee($employee))
            ->sortByDesc(fn (array $employee) => Money::toCents($employee['total_sold']))
            ->values()
            ->all();
        $canceled = $this->canceledSummary($localStart, $localEnd, $employeeId, $paymentMethod);
        $dailyRows = collect($daily)
            ->map(function (array $values, string $date) {
                $localDate = CarbonImmutable::createFromFormat('Y-m-d', $date, self::TIMEZONE)->locale('es');

                return [
                    'date' => $date,
                    'date_label' => $localDate->translatedFormat('j \\d\\e F \\d\\e Y'),
                    ...$this->formatTotals($values),
                    'sales_count' => count($values['sale_ids']),
                    'methods' => $values['methods'] ?? [],
                ];
            })
            ->sortByDesc('date')
            ->values()
            ->all();

        if ($employeeId === null) {
            $methods = collect($methods)->map(fn (array $method) => [
                'method' => $method['method'],
                'method_label' => $method['method_label'],
                'payments_count' => $method['payments_count'],
                'amount' => Money::fromCents($method['amount_cents']),
                'card_fee_amount' => Money::fromCents($method['fee_cents']),
                'net_amount' => Money::fromCents($method['amount_cents'] - $method['fee_cents']),
            ])->values()->all();
            $methodsByDate = $this->dailyMethods($sales, $paymentMethod);
            foreach ($dailyRows as &$day) {
                $day['methods'] = $methodsByDate[$day['date']] ?? [];
            }
            unset($day);
        } else {
            $methods = [];
        }

        return [
            'filters' => [
                'period' => $period,
                'mode' => $filters['mode'] ?? 'actual',
                'date' => $period === 'custom' ? null : $referenceDate->format('Y-m-d'),
                'month' => $period === 'month' ? $localStart->format('Y-m') : null,
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
                'canceled_sales_count' => (int) $canceled->sales_count,
                'canceled_amount' => Money::fromCents(Money::toCents((string) $canceled->amount)),
            ],
            'summary' => $summary,
            'employees' => $employeeRows,
            'payment_distribution' => $methods,
            'daily' => $dailyRows,
        ];
    }

    private function allocation(Sale $sale): array
    {
        $allocation = SaleFinancials::allocateRevenue(
            $sale->items->map(fn ($item) => Money::toCents($item->line_total))->all(),
            $sale->additionalCharges->map(fn ($charge) => Money::toCents($charge->amount))->all(),
            Money::toCents($sale->discount_amount ?? '0.00'),
            Money::toCents($sale->card_fee_amount),
        );

        $lines = collect($allocation['line_final_cents'])->map(fn (int $gross, int $index) => [
            'gross_cents' => $gross,
            'fee_cents' => $allocation['fee_allocations'][$index],
        ])->all();
        $performers = $sale->items->map(function ($item, int $index) use ($lines) {
            if (! $item->performed_by || ! $item->performedBy) {
                return null;
            }

            return [
                'id' => $item->performed_by,
                'name' => $item->performedBy->name,
                'weight' => $lines[$index]['gross_cents'],
            ];
        })->filter()->groupBy('id')->map(fn ($rows) => [
            'id' => $rows->first()['id'],
            'name' => $rows->first()['name'],
            'weight' => $rows->sum('weight'),
        ])->values();
        $charges = $sale->additionalCharges->map(function ($charge, int $index) use ($allocation, $performers) {
            $grossCents = $allocation['additional_final_cents'][$index];
            $feeCents = $allocation['additional_fee_allocations'][$index];
            if ($charge->performed_by && $charge->performedBy) {
                return [[
                    'performer_id' => $charge->performed_by,
                    'performer_name' => $charge->performedBy->name,
                    'gross_cents' => $grossCents,
                    'fee_cents' => $feeCents,
                ]];
            }
            if ($performers->count() === 1) {
                $performer = $performers->first();

                return [[
                    'performer_id' => $performer['id'],
                    'performer_name' => $performer['name'],
                    'gross_cents' => $grossCents,
                    'fee_cents' => $feeCents,
                ]];
            }

            $weights = $performers->pluck('weight')->all();
            $grossAllocations = SaleFinancials::distributeCents($weights, $grossCents);
            $feeAllocations = SaleFinancials::distributeCents($weights, $feeCents);

            return $performers->map(fn (array $performer, int $performerIndex) => [
                'performer_id' => $performer['id'],
                'performer_name' => $performer['name'],
                'gross_cents' => $grossAllocations[$performerIndex],
                'fee_cents' => $feeAllocations[$performerIndex],
            ])->all();
        })->all();

        return [
            'lines' => $lines,
            'charges' => $charges,
            'discount_cents' => Money::toCents($sale->discount_amount ?? '0.00'),
            'additional_cents' => $sale->additionalCharges->sum(fn ($charge) => Money::toCents($charge->amount)),
        ];
    }

    private function addGlobalSale(array &$target, Sale $sale, array $allocation): void
    {
        $target['gross_cents'] += Money::toCents($sale->total);
        $target['service_cents'] += array_sum(array_column($allocation['lines'], 'gross_cents'));
        $target['additional_cents'] += $allocation['additional_cents'];
        $target['discount_cents'] += $allocation['discount_cents'];
        $target['fee_cents'] += Money::toCents($sale->card_fee_amount);
        $target['net_cents'] += Money::toCents($sale->net_amount);
        $target['services_count'] += $sale->total_services;
        $target['sale_ids'][$sale->id] = true;
    }

    private function addService(array &$target, Sale $sale, $item, array $line): void
    {
        $target['gross_cents'] += $line['gross_cents'];
        $target['service_cents'] += $line['gross_cents'];
        $target['fee_cents'] += $line['fee_cents'];
        $target['net_cents'] += $line['gross_cents'] - $line['fee_cents'];
        $target['services_count'] += $item->quantity;
        $target['sale_ids'][$sale->id] = true;
    }

    private function addCharge(array &$target, array $charge): void
    {
        $target['gross_cents'] += $charge['gross_cents'];
        $target['fee_cents'] += $charge['fee_cents'];
        $target['net_cents'] += $charge['gross_cents'] - $charge['fee_cents'];
    }

    private function addPayments(array &$methods, Sale $sale, ?string $paymentMethod): void
    {
        foreach ($sale->payments as $payment) {
            if ($paymentMethod !== null && $payment->method !== $paymentMethod) {
                continue;
            }
            $methods[$payment->method]['payments_count']++;
            $methods[$payment->method]['amount_cents'] += Money::toCents($payment->amount);
            $methods[$payment->method]['fee_cents'] += Money::toCents($payment->card_fee_amount);
        }
    }

    private function dailyMethods(Builder $sales, ?string $paymentMethod): array
    {
        $daily = [];
        foreach ((clone $sales)->with('payments')->lazyById(self::CHUNK_SIZE, 'sales.id', 'id') as $sale) {
            $date = $sale->sold_at->setTimezone(self::TIMEZONE)->format('Y-m-d');
            foreach ($sale->payments as $payment) {
                if ($paymentMethod !== null && $payment->method !== $paymentMethod) {
                    continue;
                }
                $daily[$date][$payment->method] ??= ['method' => $payment->method, 'method_label' => $this->methodLabel($payment->method), 'amount_cents' => 0];
                $daily[$date][$payment->method]['amount_cents'] += Money::toCents($payment->amount);
            }
        }

        return collect($daily)->map(fn (array $day) => collect($day)->map(fn (array $method) => [
            'method' => $method['method'],
            'method_label' => $method['method_label'],
            'amount' => Money::fromCents($method['amount_cents']),
        ])->values()->all())->all();
    }

    private function emptyTotals(): array
    {
        return ['gross_cents' => 0, 'service_cents' => 0, 'additional_cents' => 0, 'discount_cents' => 0, 'fee_cents' => 0, 'net_cents' => 0, 'services_count' => 0, 'sale_ids' => []];
    }

    private function emptyEmployee(int $id, string $name): array
    {
        return [...$this->emptyTotals(), 'id' => $id, 'name' => $name];
    }

    private function formatTotals(array $totals): array
    {
        $salesCount = count($totals['sale_ids']);
        return [
            'total_sold' => Money::fromCents($totals['gross_cents']),
            'service_revenue' => Money::fromCents($totals['service_cents']),
            'additional_charges' => Money::fromCents($totals['additional_cents']),
            'discount_amount' => Money::fromCents($totals['discount_cents']),
            'card_fee_amount' => Money::fromCents($totals['fee_cents']),
            'net_amount' => Money::fromCents($totals['net_cents']),
            'sales_count' => $salesCount,
            'services_count' => $totals['services_count'],
            'average_sale' => Money::fromCents($salesCount === 0 ? 0 : intdiv($totals['gross_cents'] + intdiv($salesCount, 2), $salesCount)),
        ];
    }

    private function formatEmployee(array $employee): array
    {
        $formatted = $this->formatTotals($employee);

        return [
            'id' => $employee['id'],
            'name' => $employee['name'],
            'services_count' => $formatted['services_count'],
            'total_sold' => $formatted['total_sold'],
            'card_fee_amount' => $formatted['card_fee_amount'],
            'net_amount' => $formatted['net_amount'],
        ];
    }

    private function emptyMethods(): array
    {
        return collect([Sale::PAYMENT_METHOD_CASH, Sale::PAYMENT_METHOD_CARD, Sale::PAYMENT_METHOD_TRANSFER])
            ->mapWithKeys(fn (string $method) => [$method => ['method' => $method, 'method_label' => $this->methodLabel($method), 'payments_count' => 0, 'amount_cents' => 0, 'fee_cents' => 0]])
            ->all();
    }

    private function methodLabel(string $method): string
    {
        return match ($method) {
            Sale::PAYMENT_METHOD_CARD => 'Tarjeta',
            Sale::PAYMENT_METHOD_TRANSFER => 'Transferencia',
            default => 'Efectivo',
        };
    }

    private function salesQuery(CarbonImmutable $start, CarbonImmutable $end, mixed $paymentMethod): Builder
    {
        return Sale::query()
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->where('sales.sold_at', '>=', $start->utc())
            ->where('sales.sold_at', '<', $end->utc())
            ->when($paymentMethod !== null, fn (Builder $query) => $query->whereHas('payments', fn (Builder $payments) => $payments->where('method', $paymentMethod)));
    }

    private function canceledSummary(CarbonImmutable $start, CarbonImmutable $end, ?int $employeeId, ?string $paymentMethod): object
    {
        return Sale::query()
            ->where('sales.status', Sale::STATUS_CANCELED)
            ->where('sales.sold_at', '>=', $start->utc())
            ->where('sales.sold_at', '<', $end->utc())
            ->when($employeeId !== null, fn (Builder $query) => $query->where(function (Builder $scope) use ($employeeId) {
                $scope->whereHas('items', fn (Builder $items) => $items->where('performed_by', $employeeId))
                    ->orWhereHas('additionalCharges', fn (Builder $charges) => $charges->where('performed_by', $employeeId));
            }))
            ->when($paymentMethod !== null, fn (Builder $query) => $query->whereHas('payments', fn (Builder $payments) => $payments->where('method', $paymentMethod)))
            ->toBase()->selectRaw('COUNT(*) as sales_count')->selectRaw('COALESCE(SUM(total), 0) as amount')->first();
    }
}

<?php

namespace App\Actions\Reports;

use App\Models\Expense;
use App\Models\User;
use App\Support\Money;
use App\Support\ReportPeriod;

final class BuildExpensesSummaryAction
{
    public function execute(array $filters, ?User $user = null, bool $includeCategoryBreakdown = true): array
    {
        $period = $filters['period'] ?? 'today';
        [$localStart, $localEnd, $referenceDate] = ReportPeriod::bounds($filters);
        $query = Expense::query()
            ->when($user, fn ($query) => $query->visibleTo($user))
            ->where('status', Expense::STATUS_RECORDED)
            ->where('expense_date', '>=', $localStart->toDateString())
            ->where('expense_date', '<', $localEnd->toDateString());
        $total = (clone $query)->toBase()
            ->selectRaw('COUNT(*) as expenses_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as amount')
            ->first();
        $categories = $includeCategoryBreakdown
            ? (clone $query)->toBase()
                ->select('category_name_snapshot')
                ->selectRaw('COUNT(*) as expenses_count')
                ->selectRaw('COALESCE(SUM(amount), 0) as amount')
                ->groupBy('category_name_snapshot')
                ->orderByRaw('SUM(amount) DESC')
                ->get()
                ->map(fn ($row) => [
                    'category_name' => $row->category_name_snapshot,
                    'expenses_count' => (int) $row->expenses_count,
                    'total' => Money::fromCents(Money::toCents((string) $row->amount)),
                ])->values()->all()
            : null;
        $methodRows = (clone $query)->toBase()
            ->select('payment_method')
            ->selectRaw('COUNT(*) as expenses_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as amount')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');
        $methods = collect([
            Expense::PAYMENT_METHOD_CASH => 'Efectivo',
            Expense::PAYMENT_METHOD_CARD => 'Tarjeta',
            Expense::PAYMENT_METHOD_TRANSFER => 'Transferencia',
        ])->map(function (string $label, string $method) use ($methodRows) {
            $row = $methodRows->get($method);

            return [
                'method' => $method,
                'method_label' => $label,
                'expenses_count' => (int) ($row?->expenses_count ?? 0),
                'total' => Money::fromCents(Money::toCents((string) ($row?->amount ?? '0'))),
            ];
        })->values()->all();

        return [
            'filters' => [
                'period' => $period,
                'mode' => $filters['mode'] ?? 'actual',
                'date' => $period === 'custom' ? null : $referenceDate->format('Y-m-d'),
                'month' => $period === 'month' ? $localStart->format('Y-m') : null,
                'date_from' => $period === 'custom' ? $localStart->format('Y-m-d') : null,
                'date_to' => $period === 'custom' ? $localEnd->subDay()->format('Y-m-d') : null,
                'employee_id' => $filters['employee_id'] ?? null,
                'payment_method' => $filters['payment_method'] ?? null,
            ],
            'period' => [
                'label' => ReportPeriod::label($period, $localStart, $localEnd),
                'start_date' => $localStart->format('Y-m-d'),
                'end_date' => $localEnd->subDay()->format('Y-m-d'),
                'timezone' => ReportPeriod::TIMEZONE,
                'week_starts_on' => 'monday',
            ],
            'expense_actual' => [
                'paid_expenses' => Money::fromCents(Money::toCents((string) $total->amount)),
                'expenses_count' => (int) $total->expenses_count,
            ],
            ...($includeCategoryBreakdown ? ['expense_categories' => $categories] : []),
            'expense_payment_distribution' => $methods,
        ];
    }
}

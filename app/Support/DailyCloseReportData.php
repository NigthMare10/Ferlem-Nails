<?php

namespace App\Support;

use App\Actions\Reports\BuildAppointmentProjectionAction;
use App\Actions\Reports\BuildExpensesSummaryAction;
use App\Actions\Reports\BuildSalesSummaryAction;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;

final class DailyCloseReportData
{
    private const DETAIL_LIMIT = 250;

    public function __construct(
        private readonly BuildSalesSummaryAction $salesReport,
        private readonly BuildExpensesSummaryAction $expensesReport,
        private readonly BuildAppointmentProjectionAction $projectionReport,
    ) {}

    public function build(CarbonImmutable $date, User $viewer, string $generatedBy): array
    {
        $filters = ['period' => 'today', 'mode' => 'both', 'date' => $date->format('Y-m-d')];
        $sales = $this->salesReport->execute($filters);
        $expenses = $this->expensesReport->execute($filters, $viewer);
        $projection = $this->projectionReport->execute($filters);
        $grossCents = Money::toCents($sales['actual']['gross_revenue']);

        $employees = collect($sales['employees'])->map(function (array $employee) use ($filters, $grossCents, $projection) {
            $employeeReport = $this->salesReport->execute([...$filters, 'employee_id' => $employee['id']]);
            $projected = collect($projection['projection_employees'] ?? [])->firstWhere('id', $employee['id']);

            return [
                ...$employee,
                'payment_methods' => collect($employeeReport['payment_distribution'])->filter(fn (array $method) => $method['payments_count'] > 0)->values()->all(),
                'employee_commission' => null,
                'deductions' => null,
                'participation_percentage' => $grossCents === 0 ? '0.00' : number_format(Money::toCents($employee['total_sold']) * 100 / $grossCents, 2, '.', ''),
                'projected_income' => $projected['projected_gross'] ?? '0.00',
                'projected_services_count' => $projected['services_count'] ?? 0,
            ];
        })->values()->all();

        $start = $date->startOfDay();
        $end = $start->addDay();
        $salesRows = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('sold_at', '>=', $start->utc())
            ->where('sold_at', '<', $end->utc())
            ->with([
                'items:id,sale_id,service_name,quantity,performed_by',
                'items.performedBy:id,name',
                'payments:id,sale_id,method',
            ])
            ->orderBy('sold_at')
            ->limit(self::DETAIL_LIMIT + 1)
            ->get(['id', 'sale_number', 'sold_at', 'client_name', 'total', 'card_fee_amount', 'net_amount']);
        $salesTruncated = $salesRows->count() > self::DETAIL_LIMIT;
        $salesDetails = $salesRows
            ->take(self::DETAIL_LIMIT)
            ->map(fn (Sale $sale) => [
                'reference' => $sale->sale_number,
                'time' => $sale->sold_at->setTimezone(ReportPeriod::TIMEZONE)->format('h:i a'),
                'client' => $sale->client_name ?: 'Consumidor final',
                'employees' => $sale->items->pluck('performedBy.name')->filter()->unique()->implode(', '),
                'services' => $sale->items->map(fn ($item) => $item->service_name.($item->quantity > 1 ? ' x'.$item->quantity : ''))->implode(', '),
                'payment_methods' => $sale->payments->map(fn ($payment) => match ($payment->method) {
                    Sale::PAYMENT_METHOD_CARD => 'Tarjeta',
                    Sale::PAYMENT_METHOD_TRANSFER => 'Transferencia',
                    default => 'Efectivo',
                })->unique()->implode(', '),
                'gross' => $sale->total,
                'fee' => $sale->card_fee_amount,
                'net' => $sale->net_amount,
            ])->all();

        $expenseRows = Expense::query()
            ->visibleTo($viewer)
            ->where('status', Expense::STATUS_RECORDED)
            ->whereDate('expense_date', $date->toDateString())
            ->orderBy('category_name_snapshot')
            ->orderBy('id')
            ->limit(self::DETAIL_LIMIT + 1)
            ->get(['expense_number', 'category_name_snapshot', 'description', 'payment_method', 'amount']);
        $expensesTruncated = $expenseRows->count() > self::DETAIL_LIMIT;
        $expenseDetails = $expenseRows
            ->take(self::DETAIL_LIMIT)
            ->map(fn (Expense $expense) => [
                'reference' => $expense->expense_number,
                'category' => $expense->category_name_snapshot,
                'description' => $expense->description,
                'payment_method' => match ($expense->payment_method) {
                    Expense::PAYMENT_METHOD_CARD => 'Tarjeta',
                    Expense::PAYMENT_METHOD_TRANSFER => 'Transferencia',
                    default => 'Efectivo',
                },
                'amount' => $expense->amount,
            ])->all();

        $paidExpenses = $expenses['expense_actual']['paid_expenses'];
        $available = Money::fromSignedCents(Money::toCents($sales['actual']['net_income']) - Money::toCents($paidExpenses));

        return [
            'business_name' => config('app.name', 'Studio Lemus'),
            'operational_date' => $date->locale('es')->translatedFormat('j \d\e F \d\e Y'),
            'generated_at' => CarbonImmutable::now(ReportPeriod::TIMEZONE)->locale('es')->translatedFormat('j \d\e F \d\e Y, h:i a'),
            'generated_by' => $generatedBy,
            'period' => $sales['period'],
            'actual' => [...$sales['actual'], 'paid_expenses' => $paidExpenses, 'available_result' => $available],
            'payment_distribution' => $sales['payment_distribution'],
            'employees' => $employees,
            'projection' => $projection['projection'],
            'expense_categories' => $expenses['expense_categories'],
            'expense_payment_distribution' => $expenses['expense_payment_distribution'],
            'expense_details' => $expenseDetails,
            'sales_details' => $salesDetails,
            'details_truncated' => [
                'sales' => $salesTruncated,
                'expenses' => $expensesTruncated,
                'limit' => self::DETAIL_LIMIT,
            ],
        ];
    }
}

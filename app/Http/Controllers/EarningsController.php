<?php

namespace App\Http\Controllers;

use App\Actions\Reports\BuildAppointmentProjectionAction;
use App\Actions\Reports\BuildExpensesSummaryAction;
use App\Actions\Reports\BuildSalesSummaryAction;
use App\Http\Requests\SalesEarningsRequest;
use App\Models\User;
use App\Support\Money;
use App\Support\Permissions;
use Inertia\Inertia;
use Inertia\Response;

class EarningsController extends Controller
{
    public function __invoke(
        SalesEarningsRequest $request,
        BuildSalesSummaryAction $salesReport,
        BuildAppointmentProjectionAction $projectionReport,
        BuildExpensesSummaryAction $expensesReport,
    ): Response {
        $filters = $request->validated();
        $mode = $filters['mode'];
        $canViewProjection = $request->user()->can(Permissions::APPOINTMENTS_VIEW_PROJECTION);
        $canViewSales = $request->user()->can(Permissions::REPORTS_SALES_VIEW);
        $canViewExpenses = $request->user()->can(Permissions::REPORTS_EXPENSES_VIEW);
        $payload = [
            'filters' => $filters,
            'canViewProjection' => $canViewProjection,
            'canViewSales' => $canViewSales,
            'canViewExpenses' => $canViewExpenses,
        ];

        if ($canViewSales && $mode !== 'projection') {
            $payload = [...$payload, ...$salesReport->execute($filters)];
        }
        if ($canViewExpenses && $mode !== 'projection') {
            $payload = [...$payload, ...$expensesReport->execute($filters, $request->user())];
        }
        if ($canViewProjection && $mode !== 'actual') {
            $payload = [...$payload, ...$projectionReport->execute($filters)];
        }

        $hasProjection = isset($payload['projection']);
        $actualEmployees = collect($payload['employees'] ?? [])->keyBy('id');
        $projectedEmployees = collect($payload['projection_employees'] ?? [])->keyBy('id');
        $payload['employees'] = $actualEmployees->keys()
            ->merge($projectedEmployees->keys())
            ->unique()
            ->map(function ($id) use ($actualEmployees, $hasProjection, $projectedEmployees) {
                $actual = $actualEmployees->get($id, []);
                $projected = $projectedEmployees->get($id, []);

                $row = [
                    'id' => (int) $id,
                    'name' => $actual['name'] ?? $projected['name'],
                    'sales_count' => $actual['sales_count'] ?? 0,
                    'services_count' => $actual['services_count'] ?? 0,
                    'total_sold' => $actual['total_sold'] ?? '0.00',
                    'card_fee_amount' => $actual['card_fee_amount'] ?? '0.00',
                    'net_amount' => $actual['net_amount'] ?? '0.00',
                    'average_sale' => $actual['average_sale'] ?? '0.00',
                ];

                return $hasProjection ? [
                    ...$row,
                    'projected_appointments_count' => $projected['appointments_count'] ?? 0,
                    'projected_services_count' => $projected['services_count'] ?? 0,
                    'projected_income' => $projected['projected_gross'] ?? '0.00',
                    'projected_pending_balance' => $projected['pending_balance'] ?? '0.00',
                ] : $row;
            })
            ->sortByDesc(fn (array $row) => Money::toCents($row['total_sold'])
                + Money::toCents($row['projected_income'] ?? '0.00'))
            ->values()
            ->all();
        unset($payload['projection_employees']);

        if (isset($payload['actual'], $payload['expense_actual'])) {
            $paidExpenses = $payload['expense_actual']['paid_expenses'];
            $payload['actual']['paid_expenses'] = $paidExpenses;
            $payload['actual']['available_result'] = Money::fromSignedCents(
                Money::toCents($payload['actual']['net_income']) - Money::toCents($paidExpenses),
            );
        }

        return Inertia::render('Earnings/Index', [
            ...$payload,
            'employeeOptions' => ($canViewSales || $canViewProjection) ? User::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                ->values() : [],
        ]);
    }
}

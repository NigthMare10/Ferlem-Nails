<?php

namespace App\Actions\Expenses;

use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BuildExpensesListAction
{
    public function execute(User $user, array $filters): LengthAwarePaginator
    {
        abort_unless($user->is_active, 403);

        return Expense::query()->visibleTo($user)
            ->select([
                'id', 'expense_number', 'category_id', 'category_name_snapshot', 'expense_date',
                'description', 'amount', 'payment_method', 'vendor', 'employee_id', 'status',
                'attachment_path', 'recorded_by', 'created_at',
            ])
            ->with(['employee:id,name', 'recordedBy:id,name', 'payrollObligation:id,expense_id,installment,scheduled_date'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $escaped = addcslashes($search, '%_\\');
                $query->where(function ($query) use ($escaped): void {
                    $query->where('expense_number', 'like', "%{$escaped}%")
                        ->orWhere('description', 'like', "%{$escaped}%")
                        ->orWhere('vendor', 'like', "%{$escaped}%");
                });
            })
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->where('expense_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->where(
                'expense_date',
                '<',
                CarbonImmutable::createFromFormat('!Y-m-d', $date)->addDay()->format('Y-m-d'),
            ))
            ->when($filters['category_id'] ?? null, fn ($query, int|string $id) => $query->where('category_id', (int) $id))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['payment_method'] ?? null, fn ($query, string $method) => $query->where('payment_method', $method))
            ->when($filters['employee_id'] ?? null, fn ($query, int|string $id) => $query->where('employee_id', (int) $id))
            ->when($filters['recorded_by'] ?? null, fn ($query, int|string $id) => $query->where('recorded_by', (int) $id))
            ->orderByDesc('expense_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
    }
}

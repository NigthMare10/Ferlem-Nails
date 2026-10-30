<?php

namespace App\Actions\Expenses;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseEvent;
use App\Models\User;
use App\Support\ExpenseAudit;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateExpenseAction
{
    public function __construct(private readonly PublishInternalNotificationAction $publishNotification) {}

    public function execute(User $user, Expense $expense, array $data): Expense
    {
        abort_unless($user->is_active && $user->can(Permissions::EXPENSES_UPDATE), 403);

        $updated = DB::transaction(function () use ($user, $expense, $data) {
            $locked = Expense::query()->lockForUpdate()->findOrFail($expense->getKey());
            if ($locked->payrollObligation()->exists()) {
                throw ValidationException::withMessages(['expense' => 'Los gastos de nómina automática son inmutables. Consulta su detalle en Gastos.']);
            }
            if ($locked->status !== Expense::STATUS_RECORDED) {
                throw ValidationException::withMessages(['expense' => 'Un gasto anulado no puede modificarse.']);
            }
            $locked->load('employee:id,name');
            $previous = ExpenseAudit::values($locked);
            $category = ExpenseCategory::query()->lockForUpdate()->findOrFail($data['category_id']);
            if (! $category->is_active && $category->getKey() !== $locked->category_id) {
                throw ValidationException::withMessages(['category_id' => 'La categoría seleccionada está inactiva.']);
            }
            $employee = isset($data['employee_id'])
                ? User::query()->lockForUpdate()->findOrFail($data['employee_id'])
                : null;
            if ($employee && ! $employee->is_active && $employee->getKey() !== $locked->employee_id) {
                throw ValidationException::withMessages(['employee_id' => 'El empleado relacionado debe estar activo.']);
            }

            $locked->category_id = $category->getKey();
            $locked->category_name_snapshot = $category->name;
            $locked->expense_date = $data['expense_date'];
            $locked->description = $data['description'];
            $locked->amount = Money::fromCents(Money::toCents((string) $data['amount']));
            $locked->payment_method = $data['payment_method'];
            $locked->vendor = $data['vendor'] ?? null;
            $locked->employee_id = $employee?->getKey();
            $locked->save();
            $locked->setRelation('employee', $employee);
            $current = ExpenseAudit::values($locked);

            if ($previous !== $current) {
                $event = new ExpenseEvent;
                $event->expense_id = $locked->getKey();
                $event->type = ExpenseEvent::TYPE_UPDATED;
                $event->performed_by = $user->getKey();
                $event->occurred_at = now('UTC');
                $event->previous_values = $previous;
                $event->new_values = $current;
                $event->save();

                DB::afterCommit(function () use ($event, $locked, $user): void {
                    try {
                        $this->publishNotification->execute(
                            $user,
                            'expense.updated',
                            'Gasto modificado',
                            "Se modificó el gasto {$locked->expense_number}.",
                            "/expenses/{$locked->getKey()}",
                            ['type' => 'expense', 'id' => $locked->getKey()],
                            "expense-updated:{$locked->getKey()}:{$event->getKey()}",
                            $event->occurred_at,
                            Permissions::EXPENSES_VIEW,
                        );
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                });
            }

            return $locked;
        }, 3);

        return $updated->load(['category:id,name', 'employee:id,name', 'recordedBy:id,name']);
    }
}

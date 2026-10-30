<?php

namespace App\Actions\Expenses;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\Expense;
use App\Models\ExpenseEvent;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CancelExpenseAction
{
    public function __construct(private readonly PublishInternalNotificationAction $publishNotification) {}

    public function execute(User $user, Expense $expense, string $reason): Expense
    {
        abort_unless($user->is_active && $user->can(Permissions::EXPENSES_CANCEL), 403);

        $canceled = DB::transaction(function () use ($user, $expense, $reason) {
            $locked = Expense::query()->lockForUpdate()->findOrFail($expense->getKey());
            if ($locked->payrollObligation()->exists()) {
                throw ValidationException::withMessages(['expense' => 'Los gastos de nómina automática no pueden anularse manualmente.']);
            }
            if ($locked->status !== Expense::STATUS_RECORDED) {
                throw ValidationException::withMessages(['expense' => 'Este gasto ya fue anulado.']);
            }
            $now = now('UTC');
            $locked->status = Expense::STATUS_CANCELED;
            $locked->canceled_at = $now;
            $locked->canceled_by = $user->getKey();
            $locked->cancellation_reason = $reason;
            $locked->save();

            $event = new ExpenseEvent;
            $event->expense_id = $locked->getKey();
            $event->type = ExpenseEvent::TYPE_CANCELED;
            $event->performed_by = $user->getKey();
            $event->occurred_at = $now;
            $event->previous_values = ['status' => Expense::STATUS_RECORDED];
            $event->new_values = ['status' => Expense::STATUS_CANCELED];
            $event->notes = $reason;
            $event->save();

            DB::afterCommit(function () use ($locked, $now, $user): void {
                try {
                    $this->publishNotification->execute(
                        $user,
                        'expense.canceled',
                        'Gasto anulado',
                        "Se anuló el gasto {$locked->expense_number}.",
                        "/expenses/{$locked->getKey()}",
                        ['type' => 'expense', 'id' => $locked->getKey()],
                        "expense-canceled:{$locked->getKey()}",
                        $now,
                        Permissions::EXPENSES_VIEW,
                    );
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

            return $locked;
        }, 3);

        return $canceled->load(['category:id,name', 'employee:id,name', 'recordedBy:id,name', 'canceledBy:id,name']);
    }
}

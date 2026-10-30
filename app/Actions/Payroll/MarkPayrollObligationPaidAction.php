<?php

namespace App\Actions\Payroll;

use App\Actions\Expenses\CreateExpenseAction;
use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\ExpenseCategory;
use App\Models\PayrollObligation;
use App\Models\User;
use App\Support\PayrollAudit;
use App\Support\Permissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MarkPayrollObligationPaidAction
{
    public function __construct(private CreateExpenseAction $createExpense, private PublishInternalNotificationAction $notifications) {}

    public function execute(User $actor, PayrollObligation $obligation, array $data, ?UploadedFile $attachment = null): PayrollObligation
    {
        if (! $actor->is_active || ! $actor->can(Permissions::PAYROLL_MARK_PAID) || ! $actor->can(Permissions::EXPENSES_CREATE)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $obligation, $data, $attachment) {
            $locked = PayrollObligation::query()->lockForUpdate()->with('user')->findOrFail($obligation->id);
            if ($locked->status !== PayrollObligation::STATUS_PENDING) {
                throw ValidationException::withMessages(['obligation' => 'La obligación ya no está pendiente.']);
            }
            $category = ExpenseCategory::query()->where('slug', 'nomina')->where('is_active', true)->first();
            if (! $category) {
                throw ValidationException::withMessages(['obligation' => 'La categoría Nómina debe estar activa para confirmar el pago.']);
            }
            $label = $locked->installment === PayrollObligation::INSTALLMENT_FIRST ? 'Primera quincena' : 'Segunda quincena';
            $expense = $this->createExpense->execute($actor, ['checkout_token' => $data['checkout_token'] ?? (string) Str::uuid(), 'expense_date' => $data['expense_date'], 'category_id' => $category->id, 'description' => "Pago de nómina — {$label} ".sprintf('%02d', $locked->period_month)." {$locked->period_year}", 'amount' => $locked->amount, 'payment_method' => $data['payment_method'], 'vendor' => $locked->user->name, 'employee_id' => $locked->user_id, 'notes' => $data['notes'] ?? null], $attachment);
            $locked->status = PayrollObligation::STATUS_PAID;
            $locked->paid_at = now('UTC');
            $locked->paid_by = $actor->id;
            $locked->expense_id = $expense->id;
            $locked->processing_error = null;
            $locked->processing_failed_at = null;
            $locked->save();
            PayrollAudit::record($locked, 'obligation.paid', $actor, ['status' => PayrollObligation::STATUS_PENDING], ['status' => PayrollObligation::STATUS_PAID, 'expense_number' => $expense->expense_number, 'payment_method' => $expense->payment_method, 'expense_date' => $expense->expense_date->toDateString(), 'amount' => $locked->amount]);
            DB::afterCommit(function () use ($actor, $locked): void {
                try {
                    $this->notifications->execute($actor, 'payroll.paid', 'Salario pagado', 'Se registró automáticamente un gasto de nómina.', '/expenses?section=payroll', ['type' => 'payroll_obligation', 'id' => $locked->id], "payroll-paid:{$locked->id}", $locked->paid_at, Permissions::PAYROLL_VIEW);
                } catch (Throwable $e) {
                    report($e);
                }
            });

            return $locked;
        }, 3);
    }
}

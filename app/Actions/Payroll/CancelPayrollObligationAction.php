<?php

namespace App\Actions\Payroll;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\PayrollObligation;
use App\Models\User;
use App\Support\PayrollAudit;
use App\Support\Permissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CancelPayrollObligationAction
{
    public function __construct(private PublishInternalNotificationAction $notifications) {}

    public function execute(User $actor, PayrollObligation $obligation, string $reason): PayrollObligation
    {
        if (! $actor->is_active || ! $actor->can(Permissions::PAYROLL_CANCEL_OBLIGATION)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $obligation, $reason) {
            $locked = PayrollObligation::query()->lockForUpdate()->findOrFail($obligation->id);
            if ($locked->status !== PayrollObligation::STATUS_PENDING) {
                throw ValidationException::withMessages(['obligation' => 'Solo una obligación pendiente puede cancelarse.']);
            }
            $locked->status = PayrollObligation::STATUS_CANCELED;
            $locked->canceled_at = now('UTC');
            $locked->canceled_by = $actor->id;
            $locked->cancellation_reason = $reason;
            $locked->save();
            PayrollAudit::record($locked, 'obligation.canceled', $actor, ['status' => PayrollObligation::STATUS_PENDING], ['status' => PayrollObligation::STATUS_CANCELED], $reason);
            DB::afterCommit(function () use ($actor, $locked): void {
                try {
                    $this->notifications->execute($actor, 'payroll.canceled', 'Obligación cancelada', 'Se canceló una obligación de nómina.', '/expenses?section=payroll', ['type' => 'payroll_obligation', 'id' => $locked->id], "payroll-canceled:{$locked->id}", $locked->canceled_at, Permissions::PAYROLL_VIEW);
                } catch (Throwable $e) {
                    report($e);
                }
            });

            return $locked;
        }, 3);
    }
}

<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Models\AppointmentDepositRefund;
use App\Models\AppointmentEvent;
use App\Models\AppointmentItem;
use App\Models\User;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionAppointmentStatusAction
{
    public function __construct(private ResolveAppointmentDepositAction $resolveDeposit) {}

    public function execute(
        User $user,
        Appointment $appointment,
        string $reason,
        string $status,
        string $permission,
        array $depositResolution = [],
    ): Appointment {
        if (! $user->is_active
            || ! $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS)
            || ! $user->hasPermissionTo($permission)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($user, $appointment, $reason, $status, $depositResolution) {
            $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            $locked->setRelation('items', $locked->items()->orderBy('position')->orderBy('id')->lockForUpdate()->get());
            $deposit = $locked->deposit()->lockForUpdate()->first();

            $this->authorizeScope($user, $locked);
            if ($idempotent = $this->idempotentRefund($user, $locked, $status, $depositResolution)) {
                return $idempotent;
            }
            if ($locked->status !== Appointment::STATUS_SCHEDULED) {
                throw ValidationException::withMessages([
                    'appointment' => 'Esta cita ya tiene un estado terminal y no puede modificarse nuevamente.',
                ]);
            }

            $occurredAt = CarbonImmutable::now('UTC');
            if ($status === Appointment::STATUS_NO_SHOW
                && $locked->scheduled_start->setTimezone(CreateAppointmentAction::TIMEZONE)
                    ->greaterThan($occurredAt->setTimezone(CreateAppointmentAction::TIMEZONE))) {
                throw ValidationException::withMessages([
                    'appointment' => 'Podrás marcar No llegó cuando haya comenzado la hora de la cita.',
                ]);
            }

            if ($deposit?->status === AppointmentDeposit::STATUS_PENDING) {
                $this->resolveDeposit->executeWithinTransaction($user, $deposit, $depositResolution, $occurredAt);
            } elseif (isset($depositResolution['deposit_resolution'])) {
                throw ValidationException::withMessages([
                    'deposit_resolution' => 'Esta cita no tiene un adelanto pendiente por resolver.',
                ]);
            }

            $locked->status = $status;
            if ($status === Appointment::STATUS_CANCELED) {
                $locked->canceled_at = $occurredAt;
                $locked->canceled_by = $user->getKey();
                $locked->cancellation_reason = $reason;
            } else {
                $locked->no_show_at = $occurredAt;
                $locked->no_show_by = $user->getKey();
                $locked->no_show_reason = $reason;
            }
            $locked->save();

            $event = new AppointmentEvent;
            $event->appointment_id = $locked->getKey();
            $event->type = $status === Appointment::STATUS_CANCELED
                ? AppointmentEvent::TYPE_CANCELED
                : AppointmentEvent::TYPE_NO_SHOW;
            $event->performed_by = $user->getKey();
            $event->occurred_at = $occurredAt;
            $event->previous_values = ['status' => Appointment::STATUS_SCHEDULED];
            $event->new_values = ['status' => $status];
            $event->notes = $reason;
            $event->save();

            return $locked->load([
                'assignedTo:id,name',
                'createdBy:id,name',
                'canceledBy:id,name',
                'noShowBy:id,name',
                'items.assignedTo:id,name',
                'events.performedBy:id,name',
                'deposit.recordedBy:id,name',
                'deposit.resolvedBy:id,name',
                'deposit.refunds.refundedBy:id,name',
            ]);
        }, 3);
    }

    private function idempotentRefund(User $user, Appointment $appointment, string $status, array $data): ?Appointment
    {
        $token = $data['operation_token'] ?? null;
        if (! $token) {
            return null;
        }

        $refund = AppointmentDepositRefund::query()
            ->where('operation_token', $token)
            ->with('deposit')
            ->first();
        if (! $refund) {
            return null;
        }

        $sameOperation = $refund->deposit->appointment_id === $appointment->getKey()
            && $refund->purpose === AppointmentDepositRefund::PURPOSE_TERMINAL
            && $appointment->status === $status
            && in_array($refund->deposit->status, [AppointmentDeposit::STATUS_REFUNDED, AppointmentDeposit::STATUS_PARTIALLY_REFUNDED], true);
        if (! $sameOperation) {
            throw ValidationException::withMessages(['operation_token' => 'Esta identificación de devolución ya fue utilizada.']);
        }
        if (! $user->hasPermissionTo(Permissions::APPOINTMENTS_RESOLVE_DEPOSIT)) {
            throw new AuthorizationException;
        }

        return $appointment->load([
            'assignedTo:id,name',
            'createdBy:id,name',
            'canceledBy:id,name',
            'noShowBy:id,name',
            'items.assignedTo:id,name',
            'events.performedBy:id,name',
            'deposit.recordedBy:id,name',
            'deposit.resolvedBy:id,name',
            'deposit.refunds.refundedBy:id,name',
        ]);
    }

    private function authorizeScope(User $user, Appointment $appointment): void
    {
        if ($user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL)) {
            return;
        }

        $ownsEveryItem = $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_OWN)
            && $appointment->items->isNotEmpty()
            && $appointment->items->every(fn (AppointmentItem $item) => $item->assigned_to === $user->getKey());

        if (! $ownsEveryItem) {
            throw ValidationException::withMessages([
                'appointment' => 'Esta cita incluye servicios de otras personas. Solicita a un responsable que cambie su estado.',
            ]);
        }
    }
}

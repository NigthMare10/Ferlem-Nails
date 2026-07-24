<?php

namespace App\Actions\Appointments;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Models\AppointmentDepositRefund;
use App\Models\AppointmentEvent;
use App\Models\AppointmentItem;
use App\Models\User;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundAppointmentDepositExcessAction
{
    public function __construct(private PublishInternalNotificationAction $publishNotification) {}

    public function execute(User $user, Appointment $appointment, array $data): AppointmentDeposit
    {
        $this->authorizeGlobal($user);
        $refundCents = Money::toCents($data['amount']);

        try {
            return DB::transaction(function () use ($user, $appointment, $data, $refundCents) {
                $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
                $locked->setRelation('items', $locked->items()->orderBy('position')->orderBy('id')->lockForUpdate()->get());
                $deposit = $locked->deposit()->lockForUpdate()->first();

                $this->authorizeScope($user, $locked);
                if (! $deposit) {
                    throw ValidationException::withMessages(['deposit' => 'Esta cita no tiene un adelanto pendiente.']);
                }
                if ($existing = AppointmentDepositRefund::query()->where('operation_token', $data['operation_token'])->first()) {
                    return $this->resolveExisting($existing, $deposit, $refundCents);
                }
                if ($locked->status !== Appointment::STATUS_SCHEDULED) {
                    throw ValidationException::withMessages(['appointment' => 'Solo una cita programada puede devolver un excedente del adelanto.']);
                }
                if ($deposit->status !== AppointmentDeposit::STATUS_PENDING) {
                    throw ValidationException::withMessages(['deposit' => 'El adelanto ya fue resuelto y no admite una devolución de excedente.']);
                }

                $availableBefore = $deposit->availableAmountCents();
                if ($refundCents <= 0 || $refundCents >= $availableBefore) {
                    throw ValidationException::withMessages([
                        'amount' => 'El excedente debe ser mayor que cero y menor que el saldo disponible del adelanto.',
                    ]);
                }

                $occurredAt = now('UTC');
                $refund = new AppointmentDepositRefund;
                $refund->appointment_deposit_id = $deposit->getKey();
                $refund->amount = Money::fromCents($refundCents);
                $refund->purpose = AppointmentDepositRefund::PURPOSE_EXCESS;
                $refund->refunded_at = $occurredAt;
                $refund->refunded_by = $user->getKey();
                $refund->notes = $data['note'] ?? 'Devolución de excedente antes del cobro.';
                $refund->operation_token = $data['operation_token'];
                $refund->save();

                $previousRefunded = Money::toCents($deposit->refunded_amount);
                $deposit->refunded_amount = Money::fromCents($previousRefunded + $refundCents);
                $deposit->save();

                $event = new AppointmentEvent;
                $event->appointment_id = $locked->getKey();
                $event->type = AppointmentEvent::TYPE_DEPOSIT_EXCESS_REFUNDED;
                $event->performed_by = $user->getKey();
                $event->occurred_at = $occurredAt;
                $event->previous_values = [
                    'deposit_refunded_amount' => Money::fromCents($previousRefunded),
                    'deposit_available_amount' => Money::fromCents($availableBefore),
                ];
                $event->new_values = [
                    'deposit_refunded_amount' => $deposit->refunded_amount,
                    'deposit_available_amount' => $deposit->availableAmount(),
                ];
                $event->notes = $data['note'] ?? 'Devolución de excedente antes del cobro.';
                $event->save();

                $this->publishNotification->execute(
                    $user,
                    'appointment.deposit_excess_refunded',
                    'Excedente de adelanto devuelto',
                    "Se devolvió un excedente del adelanto de la cita de {$locked->client_name}.",
                    "/appointments?appointment={$locked->getKey()}",
                    ['type' => 'appointment_deposit', 'id' => $deposit->getKey(), 'appointment_id' => $locked->getKey()],
                    "appointment-event:{$event->getKey()}",
                    $event->occurred_at,
                );

                return $deposit->fresh(['refunds']);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = AppointmentDepositRefund::query()->useWritePdo()->where('operation_token', $data['operation_token'])->first();
            $deposit = $appointment->deposit()->first();
            if ($existing && $deposit) {
                return $this->resolveExisting($existing, $deposit, $refundCents);
            }

            throw $exception;
        }
    }

    private function resolveExisting(AppointmentDepositRefund $refund, AppointmentDeposit $deposit, int $refundCents): AppointmentDeposit
    {
        $sameOperation = $refund->purpose === AppointmentDepositRefund::PURPOSE_EXCESS
            && $refund->appointment_deposit_id === $deposit->getKey()
            && Money::toCents($refund->amount) === $refundCents;
        if (! $sameOperation) {
            throw ValidationException::withMessages([
                'operation_token' => 'Esta identificación ya fue utilizada para otra devolución o monto.',
            ]);
        }

        return $deposit->fresh(['refunds']);
    }

    private function authorizeGlobal(User $user): void
    {
        if (! $user->is_active
            || ! $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS)
            || ! $user->hasPermissionTo(Permissions::APPOINTMENTS_RESOLVE_DEPOSIT)) {
            throw new AuthorizationException;
        }
    }

    private function authorizeScope(User $user, Appointment $appointment): void
    {
        if ($user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL)) {
            return;
        }
        if (! $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_OWN)
            || ! $appointment->items->contains(fn (AppointmentItem $item) => $item->assigned_to === $user->getKey())) {
            throw new AuthorizationException;
        }
    }
}

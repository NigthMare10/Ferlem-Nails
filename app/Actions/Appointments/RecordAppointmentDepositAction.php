<?php

namespace App\Actions\Appointments;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Models\AppointmentEvent;
use App\Models\User;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordAppointmentDepositAction
{
    public function __construct(private PublishInternalNotificationAction $publishNotification) {}

    public function execute(User $user, Appointment $appointment, array $data): AppointmentDeposit
    {
        $this->authorize($user, $appointment);

        return DB::transaction(function () use ($user, $appointment, $data) {
            $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize($user, $locked);

            if ($locked->status !== Appointment::STATUS_SCHEDULED) {
                throw ValidationException::withMessages(['appointment' => 'Solo una cita programada puede recibir un adelanto.']);
            }
            if ($locked->deposit()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['deposit' => 'Esta cita ya tiene un adelanto registrado.']);
            }
            if (! in_array($data['payment_method'] ?? null, [AppointmentDeposit::PAYMENT_METHOD_CASH, AppointmentDeposit::PAYMENT_METHOD_CARD], true)) {
                throw ValidationException::withMessages(['payment_method' => 'El método de pago no es válido.']);
            }

            if (! is_string($data['amount'] ?? null) || ! preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $data['amount'])) {
                throw ValidationException::withMessages(['amount' => 'El monto del adelanto no es válido.']);
            }
            $amountCents = Money::toCents($data['amount']);
            if ($amountCents <= 0) {
                throw ValidationException::withMessages(['amount' => 'El adelanto debe ser mayor que cero.']);
            }
            if ($amountCents > Money::toCents($locked->expected_total)) {
                throw ValidationException::withMessages(['amount' => 'El adelanto no puede superar el total estimado de la cita.']);
            }

            $snapshot = AppointmentDeposit::feeSnapshot($data['amount'], $data['payment_method']);
            $occurredAt = now('UTC');
            $deposit = new AppointmentDeposit;
            $deposit->appointment_id = $locked->getKey();
            $deposit->amount = $snapshot['amount'];
            $deposit->payment_method = $data['payment_method'];
            $deposit->card_fee_rate = $snapshot['card_fee_rate'];
            $deposit->card_fee_amount = $snapshot['card_fee_amount'];
            $deposit->net_amount = $snapshot['net_amount'];
            $deposit->status = AppointmentDeposit::STATUS_PENDING;
            $deposit->paid_at = $occurredAt;
            $deposit->recorded_by = $user->getKey();
            $deposit->applied_amount = '0.00';
            $deposit->refunded_amount = '0.00';
            $deposit->retained_amount = '0.00';
            $deposit->save();

            $event = new AppointmentEvent;
            $event->appointment_id = $locked->getKey();
            $event->type = AppointmentEvent::TYPE_DEPOSIT_RECORDED;
            $event->performed_by = $user->getKey();
            $event->occurred_at = $occurredAt;
            $event->new_values = [
                'deposit_amount' => $deposit->amount,
                'deposit_payment_method' => $deposit->payment_method,
                'deposit_status' => $deposit->status,
            ];
            $event->notes = $data['note'] ?? null;
            $event->save();

            $this->publishNotification->execute(
                $user,
                'appointment.deposit_recorded',
                'Adelanto registrado',
                "Se registró un adelanto para la cita de {$locked->client_name}.",
                "/appointments?appointment={$locked->getKey()}",
                ['type' => 'appointment_deposit', 'id' => $deposit->getKey(), 'appointment_id' => $locked->getKey()],
                "appointment-event:{$event->getKey()}",
                $event->occurred_at,
            );

            return $deposit;
        }, 3);
    }

    private function authorize(User $user, Appointment $appointment): void
    {
        $canView = $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL)
            || ($user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_OWN)
                && $appointment->items()->where('assigned_to', $user->getKey())->exists());

        if (! $user->is_active
            || ! $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS)
            || ! $user->hasPermissionTo(Permissions::APPOINTMENTS_MANAGE_DEPOSIT)
            || ! $canView) {
            throw new AuthorizationException;
        }
    }
}

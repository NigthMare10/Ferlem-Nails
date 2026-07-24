<?php

namespace App\Actions\Appointments;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\AppointmentDeposit;
use App\Models\AppointmentDepositRefund;
use App\Models\AppointmentEvent;
use App\Models\User;
use App\Support\Money;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ResolveAppointmentDepositAction
{
    public const FULL_REFUND = 'full_refund';

    public const FULL_RETENTION = 'full_retention';

    public const PARTIAL_REFUND = 'partial_refund';

    public function __construct(private PublishInternalNotificationAction $publishNotification) {}

    public function executeWithinTransaction(
        User $user,
        AppointmentDeposit $deposit,
        array $data,
        CarbonImmutable $occurredAt,
    ): AppointmentDeposit {
        if (! $user->is_active || ! $user->hasPermissionTo(Permissions::APPOINTMENTS_RESOLVE_DEPOSIT)) {
            throw new AuthorizationException;
        }
        if ($deposit->status !== AppointmentDeposit::STATUS_PENDING) {
            throw ValidationException::withMessages(['deposit_resolution' => 'Este adelanto ya fue resuelto.']);
        }

        $availableCents = $deposit->availableAmountCents();
        $previousRefundedCents = Money::toCents($deposit->refunded_amount);
        $previousRetainedCents = Money::toCents($deposit->retained_amount);
        if ($availableCents <= 0) {
            throw ValidationException::withMessages(['deposit_resolution' => 'Este adelanto ya no tiene saldo disponible por resolver.']);
        }
        $resolution = $data['deposit_resolution'] ?? null;
        if ($resolution === self::FULL_REFUND) {
            $refundCents = $availableCents;
            $retainedCents = 0;
            $status = AppointmentDeposit::STATUS_REFUNDED;
        } elseif ($resolution === self::FULL_RETENTION) {
            $refundCents = 0;
            $retainedCents = $availableCents;
            $status = AppointmentDeposit::STATUS_RETAINED;
        } elseif ($resolution === self::PARTIAL_REFUND) {
            $refundCents = Money::toCents($data['refund_amount'] ?? '0');
            if ($refundCents <= 0 || $refundCents >= $availableCents) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'La devolución parcial debe ser mayor que cero y menor que el saldo disponible del adelanto.',
                ]);
            }
            $retainedCents = $availableCents - $refundCents;
            $status = AppointmentDeposit::STATUS_PARTIALLY_REFUNDED;
        } else {
            throw ValidationException::withMessages([
                'deposit_resolution' => 'Selecciona cómo resolver el adelanto antes de cambiar el estado de la cita.',
            ]);
        }

        if ($refundCents > 0) {
            $refund = new AppointmentDepositRefund;
            $refund->appointment_deposit_id = $deposit->getKey();
            $refund->amount = Money::fromCents($refundCents);
            $refund->purpose = AppointmentDepositRefund::PURPOSE_TERMINAL;
            $refund->refunded_at = $occurredAt;
            $refund->refunded_by = $user->getKey();
            $refund->notes = $data['resolution_notes'] ?? null;
            $refund->operation_token = $data['operation_token'];
            $refund->save();
        }

        $deposit->status = $status;
        $deposit->refunded_amount = Money::fromCents($previousRefundedCents + $refundCents);
        $deposit->retained_amount = Money::fromCents($previousRetainedCents + $retainedCents);
        $deposit->resolved_at = $occurredAt;
        $deposit->resolved_by = $user->getKey();
        $deposit->resolution_notes = $data['resolution_notes'] ?? null;
        $deposit->save();

        $event = new AppointmentEvent;
        $event->appointment_id = $deposit->appointment_id;
        $event->type = AppointmentEvent::TYPE_DEPOSIT_RESOLVED;
        $event->performed_by = $user->getKey();
        $event->occurred_at = $occurredAt;
        $event->previous_values = ['deposit_status' => AppointmentDeposit::STATUS_PENDING];
        $event->new_values = [
            'deposit_status' => $status,
            'deposit_refunded_amount' => $deposit->refunded_amount,
            'deposit_retained_amount' => $deposit->retained_amount,
        ];
        $event->notes = $data['resolution_notes'] ?? null;
        $event->save();

        [$type, $title, $message] = match ($status) {
            AppointmentDeposit::STATUS_REFUNDED => ['appointment.deposit_refunded', 'Adelanto devuelto', 'Se devolvió completamente un adelanto.'],
            AppointmentDeposit::STATUS_PARTIALLY_REFUNDED => ['appointment.deposit_partially_refunded', 'Adelanto devuelto parcialmente', 'Se devolvió parcialmente un adelanto y se retuvo el saldo.'],
            default => ['appointment.deposit_retained', 'Adelanto retenido', 'Se retuvo completamente un adelanto.'],
        };
        $this->publishNotification->execute(
            $user,
            $type,
            $title,
            $message,
            "/appointments?appointment={$deposit->appointment_id}",
            ['type' => 'appointment_deposit', 'id' => $deposit->getKey(), 'appointment_id' => $deposit->appointment_id],
            "appointment-event:{$event->getKey()}",
            $event->occurred_at,
        );

        return $deposit;
    }
}

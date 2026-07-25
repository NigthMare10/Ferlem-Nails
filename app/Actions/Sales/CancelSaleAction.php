<?php

namespace App\Actions\Sales;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Sale;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelSaleAction
{
    public function __construct(private readonly PublishInternalNotificationAction $publishNotification) {}

    public function execute(User $user, Sale $sale, string $reason): Sale
    {
        abort_unless($user->is_active && $user->can(Permissions::SALES_CANCEL), 403);

        $canceled = DB::transaction(function () use ($user, $sale, $reason) {
            $locked = Sale::query()->lockForUpdate()->findOrFail($sale->getKey());
            if ($locked->status !== Sale::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'sale' => 'Esta venta ya fue anulada y no puede volver a anularse.',
                ]);
            }

            $appointment = $locked->appointment_id
                ? Appointment::query()->lockForUpdate()->findOrFail($locked->appointment_id)
                : null;
            if ($appointment && $appointment->status !== Appointment::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'sale' => 'La cita vinculada ya no conserva un estado válido para anular esta venta.',
                ]);
            }

            $now = now('UTC');
            $locked->status = Sale::STATUS_CANCELED;
            $locked->canceled_at = $now;
            $locked->canceled_by = $user->getKey();
            $locked->cancellation_reason = $reason;
            $locked->save();

            if ($appointment) {
                $event = new AppointmentEvent;
                $event->appointment_id = $appointment->getKey();
                $event->type = AppointmentEvent::TYPE_SALE_CANCELED;
                $event->performed_by = $user->getKey();
                $event->occurred_at = $now;
                $event->previous_values = ['sale_status' => Sale::STATUS_COMPLETED];
                $event->new_values = [
                    'sale_status' => Sale::STATUS_CANCELED,
                    'sale_id' => $locked->getKey(),
                    'sale_number' => $locked->sale_number,
                ];
                $event->notes = $reason;
                $event->save();
            }

            DB::afterCommit(function () use ($user, $locked, $now): void {
                $this->publishNotification->execute(
                    $user,
                    'sale.canceled',
                    'Venta anulada',
                    "Se anuló la venta {$locked->sale_number}.",
                    "/sales/{$locked->getKey()}/receipt",
                    ['type' => 'sale', 'id' => $locked->getKey(), 'appointment_id' => $locked->appointment_id],
                    "sale-canceled:{$locked->getKey()}",
                    $now,
                );
            });

            return $locked;
        }, 3);

        return $canceled->load(['soldBy:id,name', 'canceledBy:id,name', 'appointment', 'items.performedBy:id,name', 'payments']);
    }
}

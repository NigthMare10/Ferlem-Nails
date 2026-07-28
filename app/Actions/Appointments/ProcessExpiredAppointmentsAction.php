<?php

namespace App\Actions\Appointments;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Sale;
use App\Models\User;
use App\Support\AppointmentCheckoutWindow;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ProcessExpiredAppointmentsAction
{
    private const AUTOMATIC_NO_SHOW_REASON = 'Marcada automáticamente al vencer el tiempo disponible para cobrar.';

    public function __construct(private readonly PublishInternalNotificationAction $publishNotification) {}

    public function execute(?int $appointmentId = null): array
    {
        $now = CarbonImmutable::now(CreateAppointmentAction::TIMEZONE);
        $ids = Appointment::query()
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->when($appointmentId, fn ($query) => $query->whereKey($appointmentId))
            ->whereHas('items', fn ($query) => $query->where('scheduled_end', '<=', $now->utc()))
            ->pluck('id');
        $processed = ['pending' => 0, 'expired' => 0];

        foreach ($ids as $id) {
            $result = DB::transaction(function () use ($id, $now): ?string {
                $appointment = Appointment::query()->whereKey($id)->lockForUpdate()->first();
                if (! $appointment || $appointment->status !== Appointment::STATUS_SCHEDULED
                    || Sale::query()->where('appointment_id', $id)->exists()) {
                    return null;
                }
                $items = $appointment->items()->orderBy('position')->orderBy('id')->lockForUpdate()->get();
                $appointment->setRelation('items', $items);
                $endsAt = AppointmentCheckoutWindow::endsAt($appointment, $items);
                if ($now->lessThan($endsAt)) {
                    return null;
                }
                $recipientIds = $this->recipientIds($appointment, $items);
                $deadline = AppointmentCheckoutWindow::deadline($appointment, $items);
                if ($now->greaterThan($deadline)) {
                    $appointment->status = Appointment::STATUS_NO_SHOW;
                    $appointment->no_show_at = $now->utc();
                    $appointment->no_show_by = null;
                    $appointment->no_show_reason = self::AUTOMATIC_NO_SHOW_REASON;
                    $appointment->save();

                    $event = new AppointmentEvent;
                    $event->appointment_id = $appointment->getKey();
                    $event->type = AppointmentEvent::TYPE_NO_SHOW;
                    $event->performed_by = null;
                    $event->occurred_at = $now->utc();
                    $event->previous_values = ['status' => Appointment::STATUS_SCHEDULED];
                    $event->new_values = ['status' => Appointment::STATUS_NO_SHOW];
                    $event->notes = self::AUTOMATIC_NO_SHOW_REASON;
                    $event->save();

                    $this->publishNotification->executeForRecipients(
                        null, 'appointment.expired', 'Cita marcada como No llegó',
                        "La cita de {$appointment->client_name} venció sin registrar cobro.",
                        '/appointments/history', ['type' => 'appointment', 'id' => $appointment->getKey()],
                        "appointment:{$appointment->getKey()}:expired", $now, $recipientIds,
                    );

                    return 'expired';
                }

                $this->publishNotification->executeForRecipients(
                    null, 'appointment.checkout_pending', 'Cita pendiente de cobro',
                    'Tienes '.config('appointments.checkout_grace_minutes')." minutos para cobrar la cita de {$appointment->client_name}. Si no se registra el cobro, se marcará automáticamente como No llegó.",
                    "/appointments?appointment={$appointment->getKey()}", ['type' => 'appointment', 'id' => $appointment->getKey()],
                    "appointment:{$appointment->getKey()}:checkout-grace", $now, $recipientIds,
                );

                return 'pending';
            }, 3);
            if ($result !== null) {
                $processed[$result]++;
            }
        }

        return $processed;
    }

    private function recipientIds(Appointment $appointment, $items): array
    {
        $managers = User::query()
            ->where('is_active', true)
            ->role(['owner', 'administrator'])
            ->permission(Permissions::NOTIFICATIONS_ACCESS)
            ->permission(Permissions::APPOINTMENTS_VIEW_ALL)
            ->pluck('id');

        return $managers->merge($items->pluck('assigned_to'))->unique()->values()->all();
    }
}

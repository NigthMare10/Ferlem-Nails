<?php

namespace App\Actions\Appointments;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AppointmentItem;
use App\Models\Service;
use App\Models\User;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class CreateAppointmentAction
{
    public const TIMEZONE = 'America/Tegucigalpa';

    private const MAX_AMOUNT_CENTS = 999999999999;

    public function __construct(
        private RecordAppointmentDepositAction $recordDeposit,
        private PublishInternalNotificationAction $publishNotification,
    ) {}

    public function execute(User $user, array $data): Appointment
    {
        if (! $user->is_active || ! $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS) || ! $user->hasPermissionTo(Permissions::APPOINTMENTS_CREATE)) {
            throw new AuthorizationException;
        }

        $items = array_values($data['items']);
        if (! $user->hasPermissionTo(Permissions::APPOINTMENTS_ASSIGN) && collect($items)->contains(fn (array $item) => (int) $item['assigned_to'] !== $user->getKey())) {
            throw ValidationException::withMessages(['items' => 'Solo puedes crear servicios asignados a tu usuario.']);
        }

        $scheduledStart = $this->parseScheduledStart($data['date'], $data['start_time']);

        return DB::transaction(function () use ($user, $data, $items, $scheduledStart) {
            $assigneeIds = collect($items)->pluck('assigned_to')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            $assignees = User::query()->whereKey($assigneeIds->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($assigneeIds as $id) {
                $assignee = $assignees->get($id);
                if (! $assignee || ! $assignee->is_active || ! $assignee->hasPermissionTo(Permissions::APPOINTMENTS_PERFORM)) {
                    throw ValidationException::withMessages(['items' => 'Una persona seleccionada no está disponible para realizar citas.']);
                }
            }

            $serviceIds = collect($items)->pluck('service_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            $services = Service::query()->whereKey($serviceIds->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $segmentStart = $scheduledStart;
            $totalCents = 0;
            $prepared = [];

            foreach ($items as $position => $selected) {
                $service = $services->get((int) $selected['service_id']);
                if (! $service || ! $service->is_active) {
                    throw ValidationException::withMessages(['items' => 'Uno de los servicios ya no está disponible.']);
                }

                $unitPriceCents = $this->decimalToCents($service->price);
                $lineTotalCents = $unitPriceCents * (int) $selected['quantity'];
                if ($lineTotalCents > self::MAX_AMOUNT_CENTS || $totalCents + $lineTotalCents > self::MAX_AMOUNT_CENTS) {
                    throw ValidationException::withMessages(['items' => 'El total estimado de la cita excede el monto permitido.']);
                }

                $segmentEnd = $segmentStart->addMinutes((int) $selected['duration_minutes'] * (int) $selected['quantity']);
                $prepared[] = compact('service', 'selected', 'segmentStart', 'segmentEnd', 'position') + [
                    'unit_price' => $this->centsToDecimal($unitPriceCents),
                    'line_total' => $this->centsToDecimal($lineTotalCents),
                ];
                $totalCents += $lineTotalCents;
                $segmentStart = $segmentEnd;
            }

            if (! $scheduledStart->isSameDay($segmentStart)) {
                throw ValidationException::withMessages(['start_time' => 'La cita debe comenzar y terminar el mismo día.']);
            }
            $close = CarbonImmutable::createFromFormat('!Y-m-d H:i', $scheduledStart->format('Y-m-d').' '.config('appointments.close_time'), self::TIMEZONE);
            if ($segmentStart->greaterThan($close)) {
                throw ValidationException::withMessages(['start_time' => 'La cita termina fuera del horario operativo.']);
            }

            $this->ensureNoConflicts($prepared);

            $appointment = new Appointment;
            $appointment->client_name = $data['client_name'];
            $appointment->client_phone = $data['client_phone'] ?? null;
            $appointment->assigned_to = $prepared[0]['selected']['assigned_to']; // Legacy primary assignee.
            $appointment->scheduled_start = $scheduledStart->utc();
            $appointment->scheduled_end = $segmentStart->utc();
            $appointment->expected_total = $this->centsToDecimal($totalCents);
            $appointment->expected_duration_minutes = $scheduledStart->diffInMinutes($segmentStart);
            $appointment->status = Appointment::STATUS_SCHEDULED;
            $appointment->notes = $data['notes'] ?? null;
            $appointment->created_by = $user->getKey();
            $appointment->save();

            foreach ($prepared as $entry) {
                $service = $entry['service'];
                $selected = $entry['selected'];
                $item = new AppointmentItem;
                $item->appointment_id = $appointment->getKey();
                $item->service_id = $service->getKey();
                $item->service_name = $service->name;
                $item->service_description = $service->description;
                $item->assigned_to = $selected['assigned_to'];
                $item->position = $entry['position'] + 1;
                $item->scheduled_start = $entry['segmentStart']->utc();
                $item->scheduled_end = $entry['segmentEnd']->utc();
                $item->default_duration_minutes = $service->duration_minutes;
                $item->duration_minutes = $selected['duration_minutes'];
                $item->unit_price = $entry['unit_price'];
                $item->quantity = $selected['quantity'];
                $item->line_total = $entry['line_total'];
                $item->save();
            }

            $appointment->load('items.assignedTo:id,name');
            $event = new AppointmentEvent;
            $event->appointment_id = $appointment->getKey();
            $event->type = AppointmentEvent::TYPE_CREATED;
            $event->performed_by = $user->getKey();
            $event->occurred_at = now('UTC');
            $event->new_values = ['items' => $this->eventItems($appointment->items), 'status' => Appointment::STATUS_SCHEDULED];
            $event->save();

            $this->publishNotification->execute(
                $user,
                'appointment.created',
                'Cita creada',
                "Se creó la cita de {$appointment->client_name}.",
                "/appointments?appointment={$appointment->getKey()}",
                ['type' => 'appointment', 'id' => $appointment->getKey()],
                "appointment-event:{$event->getKey()}",
                $event->occurred_at,
            );

            if ($data['has_deposit'] ?? false) {
                $this->recordDeposit->execute($user, $appointment, $data['deposit']);
            }

            return $appointment->load(['assignedTo:id,name', 'items.assignedTo:id,name', 'deposit']);
        }, 3);
    }

    private function ensureNoConflicts(array $prepared, ?int $excludeAppointmentId = null): void
    {
        foreach ($prepared as $entry) {
            $conflict = AppointmentItem::query()
                ->where('assigned_to', $entry['selected']['assigned_to'])
                ->where('scheduled_start', '<', $entry['segmentEnd']->utc())
                ->where('scheduled_end', '>', $entry['segmentStart']->utc())
                ->whereHas('appointment', function ($query) use ($excludeAppointmentId) {
                    $query->where('status', Appointment::STATUS_SCHEDULED);
                    if ($excludeAppointmentId) {
                        $query->whereKeyNot($excludeAppointmentId);
                    }
                })
                ->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['start_time' => 'Una persona seleccionada ya tiene un servicio en ese horario.']);
            }
        }
    }

    private function eventItems($items): array
    {
        return collect($items)->map(fn (AppointmentItem $item) => [
            'id' => $item->getKey(),
            'service_name' => $item->service_name,
            'assigned_to' => ['name' => $item->assignedTo->name],
            'default_duration_minutes' => $item->default_duration_minutes,
            'duration_minutes' => $item->duration_minutes,
            'scheduled_start' => $item->scheduled_start->utc()->toIso8601String(),
            'scheduled_end' => $item->scheduled_end->utc()->toIso8601String(),
        ])->all();
    }

    private function parseScheduledStart(string $date, string $startTime): CarbonImmutable
    {
        $scheduledStart = CarbonImmutable::createFromFormat('!Y-m-d H:i', "$date $startTime", self::TIMEZONE);
        $slotMinutes = (int) config('appointments.slot_minutes');
        if ($scheduledStart->minute % $slotMinutes !== 0) {
            throw ValidationException::withMessages(['start_time' => "La hora de inicio debe usar intervalos de {$slotMinutes} minutos."]);
        }
        if ($scheduledStart->lessThan(CarbonImmutable::now(self::TIMEZONE))) {
            throw ValidationException::withMessages(['date' => 'No puedes crear una cita en el pasado.']);
        }
        $open = CarbonImmutable::createFromFormat('!Y-m-d H:i', $scheduledStart->format('Y-m-d').' '.config('appointments.open_time'), self::TIMEZONE);
        $close = CarbonImmutable::createFromFormat('!Y-m-d H:i', $scheduledStart->format('Y-m-d').' '.config('appointments.close_time'), self::TIMEZONE);
        if ($scheduledStart->lessThan($open) || $scheduledStart->greaterThanOrEqualTo($close)) {
            throw ValidationException::withMessages(['start_time' => 'La hora de inicio está fuera del horario operativo.']);
        }

        return $scheduledStart;
    }

    private function decimalToCents(string $amount): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new LogicException('El precio almacenado no tiene un formato decimal válido.');
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function centsToDecimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}

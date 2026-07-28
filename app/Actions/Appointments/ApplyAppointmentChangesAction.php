<?php

namespace App\Actions\Appointments;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AppointmentItem;
use App\Models\User;
use App\Support\BusinessHours;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyAppointmentChangesAction
{
    public function __construct(private PublishInternalNotificationAction $publishNotification) {}

    public function execute(User $user, Appointment $appointment, array $data, bool $reschedule): Appointment
    {
        if (! $user->is_active || ! $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS) || ! $user->hasPermissionTo(Permissions::APPOINTMENTS_UPDATE)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($user, $appointment, $data, $reschedule) {
            $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            $locked->setRelation('items', $locked->items()->orderBy('position')->orderBy('id')->lockForUpdate()->get());
            $locked->load('items.assignedTo:id,name');
            $this->authorizeScope($user, $locked);
            if ($locked->status !== Appointment::STATUS_SCHEDULED) {
                throw ValidationException::withMessages(['appointment' => 'Solo las citas programadas pueden modificarse.']);
            }
            if (! $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL)
                && $locked->items->contains(fn (AppointmentItem $item) => $item->assigned_to !== $user->getKey())) {
                throw ValidationException::withMessages([
                    'appointment' => 'Esta cita incluye servicios de otras personas. Solicita a un responsable que la modifique.',
                ]);
            }
            $previous = $this->snapshot($locked);

            if (! $reschedule) {
                $locked->client_name = $data['client_name'];
                $locked->client_phone = $data['client_phone'] ?? null;
                $locked->notes = $data['notes'] ?? null;
                $locked->save();
            } else {
                if (! $locked->scheduled_start->setTimezone(CreateAppointmentAction::TIMEZONE)
                    ->greaterThan(CarbonImmutable::now(CreateAppointmentAction::TIMEZONE))) {
                    throw ValidationException::withMessages(['appointment' => 'No puedes reprogramar una cita que ya comenzó.']);
                }
                $assignments = collect($data['assignments'] ?? [])->keyBy('appointment_item_id');
                $knownIds = $locked->items->pluck('id')->all();
                if ($assignments->keys()->diff($knownIds)->isNotEmpty()) {
                    throw ValidationException::withMessages(['assignments' => 'Una asignación no pertenece a esta cita.']);
                }
                if ($assignments->isNotEmpty() && ! $user->hasPermissionTo(Permissions::APPOINTMENTS_ASSIGN)) {
                    throw ValidationException::withMessages(['assignments' => 'No tienes permiso para cambiar el personal asignado.']);
                }
                $newAssigneeIds = $locked->items->map(function (AppointmentItem $item) use ($assignments) {
                    $requested = $assignments->get($item->id)['assigned_to'] ?? $item->assigned_to;

                    return (int) $requested;
                });
                $userIds = $locked->items->pluck('assigned_to')->merge($newAssigneeIds)->unique()->sort()->values();
                $users = User::query()->whereKey($userIds->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($newAssigneeIds as $id) {
                    $assignee = $users->get($id);
                    if (! $assignee || ! $assignee->is_active || ! $assignee->hasPermissionTo(Permissions::APPOINTMENTS_PERFORM)) {
                        throw ValidationException::withMessages(['items' => 'Una persona seleccionada no está disponible para realizar citas.']);
                    }
                }

                $appointmentStart = $this->parseScheduledStart($data['date'], $data['start_time']);
                $segmentStart = $appointmentStart;
                $segments = [];
                foreach ($locked->items->sortBy('position')->values() as $index => $item) {
                    $segmentEnd = $segmentStart->addMinutes($item->duration_minutes * $item->quantity);
                    $segments[] = compact('item', 'segmentStart', 'segmentEnd') + ['assigned_to' => $newAssigneeIds[$index]];
                    $segmentStart = $segmentEnd;
                }
                if (! $appointmentStart->isSameDay($segmentStart)) {
                    throw ValidationException::withMessages(['start_time' => 'La cita debe comenzar y terminar el mismo día.']);
                }
                if (! BusinessHours::contains($appointmentStart, $segmentStart)) {
                    throw ValidationException::withMessages(['start_time' => 'La cita termina fuera del horario operativo.']);
                }
                foreach ($segments as $segment) {
                    $expiredBefore = CarbonImmutable::now(CreateAppointmentAction::TIMEZONE)->subMinutes((int) config('appointments.checkout_grace_minutes'))->utc();
                    $conflict = AppointmentItem::query()->where('assigned_to', $segment['assigned_to'])->where('scheduled_start', '<', $segment['segmentEnd']->utc())->where('scheduled_end', '>', $segment['segmentStart']->utc())->whereHas('appointment', fn ($query) => $query->where('status', Appointment::STATUS_SCHEDULED)->whereHas('items', fn ($items) => $items->where('scheduled_end', '>', $expiredBefore))->where('appointments.id', '!=', $locked->getKey()))->exists();
                    if ($conflict) {
                        throw ValidationException::withMessages(['start_time' => 'Una persona seleccionada ya tiene un servicio en ese horario.']);
                    }
                }
                foreach ($segments as $segment) {
                    $segment['item']->assigned_to = $segment['assigned_to'];
                    $segment['item']->scheduled_start = $segment['segmentStart']->utc();
                    $segment['item']->scheduled_end = $segment['segmentEnd']->utc();
                    $segment['item']->save();
                }
                $locked->assigned_to = $segments[0]['assigned_to'];
                $locked->scheduled_start = $segments[0]['segmentStart']->utc();
                $locked->scheduled_end = $segmentStart->utc();
                $locked->expected_duration_minutes = $locked->scheduled_start->diffInMinutes($locked->scheduled_end);
                $locked->save();
            }

            $locked->load('items.assignedTo:id,name');
            $new = $this->snapshot($locked);
            [$previousChanges, $newChanges] = $this->changes($previous, $new);
            $event = new AppointmentEvent;
            $event->appointment_id = $locked->getKey();
            $event->type = $reschedule ? AppointmentEvent::TYPE_RESCHEDULED : AppointmentEvent::TYPE_UPDATED;
            $event->performed_by = $user->getKey();
            $event->occurred_at = now('UTC');
            $event->previous_values = $previousChanges;
            $event->new_values = $newChanges;
            $event->notes = $reschedule ? ($data['reschedule_note'] ?? null) : null;
            $event->save();

            if ($reschedule) {
                $this->publishNotification->execute(
                    $user,
                    'appointment.rescheduled',
                    'Cita reprogramada',
                    "Se reprogramó la cita de {$locked->client_name}.",
                    "/appointments?appointment={$locked->getKey()}",
                    ['type' => 'appointment', 'id' => $locked->getKey()],
                    "appointment-event:{$event->getKey()}",
                    $event->occurred_at,
                );
            }

            return $locked->load(['assignedTo:id,name', 'createdBy:id,name', 'items.assignedTo:id,name', 'events.performedBy:id,name']);
        }, 3);
    }

    private function authorizeScope(User $user, Appointment $appointment): void
    {
        if (! $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL) && (! $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_OWN) || ! $appointment->items->contains('assigned_to', $user->getKey()))) {
            throw new AuthorizationException;
        }
    }

    private function parseScheduledStart(string $date, string $startTime): CarbonImmutable
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', "$date $startTime", CreateAppointmentAction::TIMEZONE);
        $slotMinutes = (int) config('appointments.slot_minutes');
        if ($start->minute % $slotMinutes !== 0) {
            throw ValidationException::withMessages(['start_time' => "La hora de inicio debe usar intervalos de {$slotMinutes} minutos."]);
        }
        if ($start->lessThan(CarbonImmutable::now(CreateAppointmentAction::TIMEZONE))) {
            throw ValidationException::withMessages(['date' => 'No puedes reprogramar una cita en el pasado.']);
        }
        $bounds = BusinessHours::bounds($start);
        if ($bounds === null || $start->lessThan($bounds[0]) || $start->greaterThanOrEqualTo($bounds[1])) {
            throw ValidationException::withMessages(['start_time' => 'La hora de inicio está fuera del horario operativo.']);
        }

        return $start;
    }

    private function snapshot(Appointment $appointment): array
    {
        return [
            'client_name' => $appointment->client_name,
            'client_phone' => $appointment->client_phone,
            'notes' => $appointment->notes,
            'scheduled_start' => $appointment->scheduled_start->utc()->toIso8601String(),
            'scheduled_end' => $appointment->scheduled_end->utc()->toIso8601String(),
            'items' => collect($appointment->items)->sortBy('position')->map(fn (AppointmentItem $item) => [
                'id' => $item->id, 'service_name' => $item->service_name, 'assigned_to' => ['name' => $item->assignedTo->name],
                'scheduled_start' => $item->scheduled_start->utc()->toIso8601String(), 'scheduled_end' => $item->scheduled_end->utc()->toIso8601String(),
                'duration_minutes' => $item->duration_minutes, 'default_duration_minutes' => $item->default_duration_minutes,
            ])->values()->all(),
        ];
    }

    private function changes(array $previous, array $new): array
    {
        $old = $fresh = [];
        foreach ($new as $key => $value) {
            if ($previous[$key] !== $value) {
                $old[$key] = $previous[$key];
                $fresh[$key] = $value;
            }
        }

        return [$old, $fresh];
    }
}

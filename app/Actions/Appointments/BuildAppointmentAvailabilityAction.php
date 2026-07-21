<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\User;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class BuildAppointmentAvailabilityAction
{
    public function execute(User $user, string $date, array $items, ?int $excludeAppointmentId = null): array
    {
        $this->authorizeAssignments($user, $items);

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, CreateAppointmentAction::TIMEZONE);
        $open = $this->at($day, config('appointments.open_time'));
        $close = $this->at($day, config('appointments.close_time'));
        $slotMinutes = (int) config('appointments.slot_minutes');
        $availableTimes = [];

        for ($start = $open; $start->lessThan($close); $start = $start->addMinutes($slotMinutes)) {
            if ($start->lessThan(CarbonImmutable::now(CreateAppointmentAction::TIMEZONE))) {
                continue;
            }

            $segments = $this->segments($start, $items);
            if ($segments === [] || $segments[array_key_last($segments)]['end']->greaterThan($close)) {
                continue;
            }

            if (! $this->hasConflict($segments, $excludeAppointmentId)) {
                $availableTimes[] = $start->format('H:i');
            }
        }

        return [
            'available_times' => $availableTimes,
            'has_availability' => $availableTimes !== [],
            'operating_open_time' => $open->format('H:i'),
            'operating_close_time' => $close->format('H:i'),
        ];
    }

    private function authorizeAssignments(User $user, array $items): void
    {
        if (! $user->hasPermissionTo(Permissions::APPOINTMENTS_ASSIGN)
            && collect($items)->contains(fn (array $item) => (int) $item['assigned_to'] !== $user->getKey())) {
            throw ValidationException::withMessages(['items' => 'Solo puedes consultar horarios para servicios asignados a tu usuario.']);
        }
    }

    private function segments(CarbonImmutable $start, array $items): array
    {
        $segments = [];
        foreach ($items as $item) {
            $end = $start->addMinutes((int) $item['duration_minutes'] * (int) $item['quantity']);
            $segments[] = ['assigned_to' => (int) $item['assigned_to'], 'start' => $start, 'end' => $end];
            $start = $end;
        }

        return $segments;
    }

    private function hasConflict(array $segments, ?int $excludeAppointmentId): bool
    {
        foreach ($segments as $segment) {
            if (AppointmentItem::query()
                ->where('assigned_to', $segment['assigned_to'])
                ->where('scheduled_start', '<', $segment['end']->utc())
                ->where('scheduled_end', '>', $segment['start']->utc())
                ->whereHas('appointment', function ($query) use ($excludeAppointmentId) {
                    $query->where('status', Appointment::STATUS_SCHEDULED);
                    if ($excludeAppointmentId) {
                        $query->whereKeyNot($excludeAppointmentId);
                    }
                })->exists()) {
                return true;
            }
        }

        return false;
    }

    private function at(CarbonImmutable $day, string $time): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d H:i', $day->format('Y-m-d').' '.$time, CreateAppointmentAction::TIMEZONE);
    }
}

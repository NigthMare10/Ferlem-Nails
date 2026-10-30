<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\EmployeeBreak;
use App\Models\User;
use App\Support\BusinessHours;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BuildAppointmentAvailabilityAction
{
    private Collection $busy;

    private Collection $breaks;

    public function execute(User $user, string $date, array $items, ?int $excludeAppointmentId = null): array
    {
        $this->authorizeAssignments($user, $items);

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, CreateAppointmentAction::TIMEZONE);
        $bounds = BusinessHours::bounds($day);
        if ($bounds === null) {
            return ['available_times' => [], 'has_availability' => false, 'operating_open_time' => null, 'operating_close_time' => null];
        }
        [$open, $close] = $bounds;
        $userIds = collect($items)->pluck('assigned_to')->unique();
        $this->busy = AppointmentItem::whereIn('assigned_to', $userIds)->where('scheduled_start', '<', $close->utc())->where('scheduled_end', '>', $open->utc())
            ->whereHas('appointment', fn ($q) => $q->where('status', Appointment::STATUS_SCHEDULED)->when($excludeAppointmentId, fn ($q) => $q->whereKeyNot($excludeAppointmentId)))->get();
        $this->breaks = EmployeeBreak::whereIn('user_id', $userIds)
            ->where(fn ($q) => $q->where('date', $date)->orWhere(fn ($q) => $q->whereNull('date')->where('weekday', $day->dayOfWeek)))->get();
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
            $breaks = $this->breaks->where('user_id', $segment['assigned_to']);
            if ($breaks->whereNotNull('date')->isNotEmpty()) {
                $breaks = $breaks->whereNotNull('date');
            }
            if ($breaks->contains(fn ($b) => $segment['start']->format('H:i:s') < $b->ends_at && $segment['end']->format('H:i:s') > $b->starts_at)) {
                return true;
            }
            if ($this->busy->contains(fn ($item) => $item->assigned_to === $segment['assigned_to'] && $item->scheduled_start->lessThan($segment['end']->utc()) && $item->scheduled_end->greaterThan($segment['start']->utc()))) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\User;
use App\Support\Permissions;
use Carbon\CarbonImmutable;

class BuildAppointmentCalendarAction
{
    public function execute(User $user, string $month, ?int $employeeId = null): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, CreateAppointmentAction::TIMEZONE)->startOfMonth();
        $end = $start->addMonth();
        $viewAll = $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL);
        $assigneeId = $viewAll ? $employeeId : $user->getKey();
        $expiredBefore = CarbonImmutable::now(CreateAppointmentAction::TIMEZONE)
            ->subMinutes((int) config('appointments.checkout_grace_minutes'))->utc();

        $items = AppointmentItem::query()
            ->select(['id', 'appointment_id', 'assigned_to', 'service_name', 'scheduled_start', 'scheduled_end'])
            ->with([
                'appointment:id,client_name,status',
                'assignedTo:id,name',
            ])
            ->where('scheduled_start', '>=', $start->utc())
            ->where('scheduled_start', '<', $end->utc())
            ->whereHas('appointment', fn ($query) => $query
                ->where('status', Appointment::STATUS_SCHEDULED)
                ->whereHas('items', fn ($appointmentItems) => $appointmentItems->where('scheduled_end', '>', $expiredBefore)))
            ->when($assigneeId, fn ($query) => $query->where('assigned_to', $assigneeId))
            ->orderBy('scheduled_start')
            ->get();
        $shared = AppointmentItem::query()
            ->selectRaw('appointment_id, count(distinct assigned_to) as assignees_count')
            ->whereIn('appointment_id', $items->pluck('appointment_id')->unique())
            ->groupBy('appointment_id')
            ->pluck('assignees_count', 'appointment_id');

        return $items->groupBy(fn (AppointmentItem $item) => $item->scheduled_start->setTimezone(CreateAppointmentAction::TIMEZONE)->format('Y-m-d'))
            ->map(function ($dayItems, string $date) use ($viewAll, $shared) {
                return [
                    'date' => $date,
                    'appointments_count' => $dayItems->pluck('appointment_id')->unique()->count(),
                    'services_count' => $dayItems->count(),
                    'has_appointments' => true,
                    'previews' => $dayItems->take(2)->map(fn (AppointmentItem $item) => [
                        'appointment_id' => $item->appointment_id,
                        'visible_start' => $item->scheduled_start->toIso8601String(),
                        'visible_end' => $item->scheduled_end->toIso8601String(),
                        'start_time' => $item->scheduled_start->setTimezone(CreateAppointmentAction::TIMEZONE)->format('H:i'),
                        'service_name' => $item->service_name,
                        'client_name' => $item->appointment->client_name,
                        'assigned_name' => $viewAll ? $item->assignedTo->name : null,
                        'is_shared' => ((int) ($shared[$item->appointment_id] ?? 1)) > 1,
                    ])->values(),
                ];
            })->values()->all();
    }
}

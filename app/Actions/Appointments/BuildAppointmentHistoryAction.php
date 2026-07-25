<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\User;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BuildAppointmentHistoryAction
{
    public function execute(User $user, array $filters): LengthAwarePaginator
    {
        $viewAll = $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL);
        $employeeId = isset($filters['employee_id']) ? (int) $filters['employee_id'] : null;
        $visibleEmployeeId = $viewAll ? $employeeId : $user->getKey();
        $service = $filters['service'] ?? null;

        $query = Appointment::query()
            ->select([
                'id', 'client_name', 'status', 'scheduled_start', 'scheduled_end',
                'expected_duration_minutes', 'expected_total', 'completed_at',
            ])
            ->with([
                'items' => function ($items) use ($viewAll, $user): void {
                    $items->select([
                        'id', 'appointment_id', 'assigned_to', 'service_name',
                        'duration_minutes', 'quantity', 'line_total', 'position',
                        'scheduled_start', 'scheduled_end',
                    ])
                        ->when(! $viewAll, fn ($query) => $query->where('assigned_to', $user->getKey()))
                        ->when($viewAll, fn ($query) => $query->with('assignedTo:id,name'))
                        ->orderBy('position')
                        ->orderBy('id');
                },
                'deposit:id,appointment_id,amount,status,applied_amount,refunded_amount,retained_amount',
                'sale:id,appointment_id,sold_by,sale_number,total,status,canceled_at',
            ])
            ->when($filters['date_from'] ?? null, function ($query, string $date): void {
                $start = CarbonImmutable::createFromFormat('!Y-m-d', $date, CreateAppointmentAction::TIMEZONE);
                $query->where('scheduled_start', '>=', $start->utc());
            })
            ->when($filters['date_to'] ?? null, function ($query, string $date): void {
                $end = CarbonImmutable::createFromFormat('!Y-m-d', $date, CreateAppointmentAction::TIMEZONE)->addDay();
                $query->where('scheduled_start', '<', $end->utc());
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['client'] ?? null, fn ($query, string $client) => $query->where('client_name', 'like', "%{$client}%"));

        if ($visibleEmployeeId) {
            $query->whereHas('items', function ($items) use ($visibleEmployeeId, $service): void {
                $items->where('assigned_to', $visibleEmployeeId)
                    ->when($service, fn ($query, string $value) => $query->where('service_name', 'like', "%{$value}%"));
            });
        } elseif ($service) {
            $query->whereHas('items', fn ($items) => $items->where('service_name', 'like', "%{$service}%"));
        }

        return $query
            ->orderByDesc('scheduled_start')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
    }
}

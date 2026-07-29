<?php

namespace App\Http\Resources;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $viewAll = $user?->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL) ?? false;
        $canAssign = $user?->hasPermissionTo(Permissions::APPOINTMENTS_ASSIGN) ?? false;
        $canManageDeposit = $user?->hasPermissionTo(Permissions::APPOINTMENTS_MANAGE_DEPOSIT) ?? false;
        $canResolveDeposit = $user?->hasPermissionTo(Permissions::APPOINTMENTS_RESOLVE_DEPOSIT) ?? false;
        $canCheckout = ($user?->hasPermissionTo(Permissions::APPOINTMENTS_CONVERT_TO_SALE) ?? false)
            && ($user?->hasPermissionTo(Permissions::SALES_ACCESS) ?? false)
            && ($user?->hasPermissionTo(Permissions::SALES_CREATE) ?? false);
        $deposit = $this->deposit;
        $visibleItems = $this->items
            ->when(! $viewAll, fn ($items) => $items->where('assigned_to', $user->getKey()))
            ->sortBy('position')
            ->values();
        $visibleStart = $viewAll ? $this->scheduled_start : $visibleItems->min('scheduled_start');
        $visibleEnd = $viewAll ? $this->scheduled_end : $visibleItems->max('scheduled_end');
        $visibleDuration = $viewAll
            ? $this->expected_duration_minutes
            : $visibleItems->sum(fn ($item) => $item->duration_minutes * $item->quantity);
        $visibleTotalCents = $visibleItems->sum(fn ($item) => $this->cents($item->line_total));
        $now = CarbonImmutable::now(CreateAppointmentAction::TIMEZONE);
        $endsAt = $this->scheduled_end->setTimezone(CreateAppointmentAction::TIMEZONE);
        $beforeStart = $now->lessThan($this->scheduled_start->setTimezone(CreateAppointmentAction::TIMEZONE));
        $operationalStatus = $beforeStart ? 'scheduled' : ($now->lessThanOrEqualTo($endsAt) ? 'in_service' : 'pending_checkout');

        return [
            'id' => $this->id,
            'client_name' => $this->client_name,
            'client_phone' => $this->client_phone,
            'status' => $this->status,
            'status_label' => match ($this->status) {
                'scheduled' => match ($operationalStatus) {
                    'in_service' => 'En atención',
                    'pending_checkout' => 'Pendiente de cobro',
                    default => 'Programada',
                },
                'completed' => 'Completada',
                'canceled' => 'Cancelada',
                'no_show' => 'No llegó',
                default => $this->status,
            },
            'notes' => $this->notes,
            'is_shared' => $this->items->pluck('assigned_to')->unique()->count() > 1,
            'visible_start' => $visibleStart->toIso8601String(),
            'visible_end' => $visibleEnd->toIso8601String(),
            'visible_start_time' => $visibleStart->setTimezone(CreateAppointmentAction::TIMEZONE)->format('H:i'),
            'visible_end_time' => $visibleEnd->setTimezone(CreateAppointmentAction::TIMEZONE)->format('H:i'),
            'visible_duration_minutes' => $visibleDuration,
            'visible_total' => number_format($visibleTotalCents / 100, 2, '.', ''),
            'can_reschedule' => $this->status === 'scheduled'
                && $beforeStart
                && ($canAssign || ! $this->items->contains(fn ($item) => $item->assigned_to !== $user->getKey())),
            'can_cancel' => $this->status === 'scheduled'
                && $beforeStart
                && ($viewAll || ! $this->items->contains(fn ($item) => $item->assigned_to !== $user->getKey()))
                && ($deposit?->status !== 'pending' || $canResolveDeposit),
            'can_change_status' => $this->status === 'scheduled'
                && ($viewAll || ! $this->items->contains(fn ($item) => $item->assigned_to !== $user->getKey()))
                && ($deposit?->status !== 'pending' || $canResolveDeposit),
            'can_mark_no_show_now' => $this->status === 'scheduled'
                && ! $beforeStart,
            'can_record_deposit' => $this->status === 'scheduled' && ! $deposit && $canManageDeposit,
            'has_pending_deposit' => $deposit?->status === 'pending',
            'can_resolve_deposit' => $deposit?->status === 'pending' && $canResolveDeposit,
            'can_checkout' => $this->status === 'scheduled' && ! $this->sale && $canCheckout,
            'operational_status' => $this->status === 'scheduled' ? $operationalStatus : $this->status,
            'status_reason' => match ($this->status) {
                'canceled' => $this->cancellation_reason,
                'no_show' => $this->no_show_reason,
                default => null,
            },
            'visible_items' => $visibleItems->map(fn ($item) => [
                'id' => $item->id,
                'service_id' => $item->service_id,
                'service_name' => $item->service_name,
                'duration_minutes' => $item->duration_minutes,
                'default_duration_minutes' => $item->default_duration_minutes,
                'position' => $item->position,
                'scheduled_start' => $item->scheduled_start->toIso8601String(),
                'scheduled_end' => $item->scheduled_end->toIso8601String(),
                'start_time' => $item->scheduled_start->setTimezone(CreateAppointmentAction::TIMEZONE)->format('H:i'),
                'end_time' => $item->scheduled_end->setTimezone(CreateAppointmentAction::TIMEZONE)->format('H:i'),
                'assigned_to' => ['id' => $item->assignedTo->id, 'name' => $item->assignedTo->name],
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
            ])->values(),
        ];
    }

    private function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}

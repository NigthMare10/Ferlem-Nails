<?php

namespace App\Http\Resources;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Models\Appointment;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $viewAll = $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL);
        $items = $this->items->sortBy('position')->values();
        $start = $items->min('scheduled_start') ?? $this->scheduled_start;
        $end = $items->max('scheduled_end') ?? $this->scheduled_end;
        $total = Money::fromCents($items->sum(fn ($item) => Money::toCents($item->line_total)));
        $canViewReceipt = $this->sale
            && ($user->hasRole('owner')
                || ($user->hasPermissionTo(Permissions::SALES_REPRINT)
                    && $user->hasPermissionTo(Permissions::SALES_VIEW_OWN)
                    && $this->sale->sold_by === $user->getKey()));

        return [
            'id' => $this->id,
            'client_name' => $this->client_name,
            'status' => $this->status,
            'status_label' => $this->statusLabel($this->status),
            'date' => $start->setTimezone(CreateAppointmentAction::TIMEZONE)->format('Y-m-d'),
            'date_display' => $start->setTimezone(CreateAppointmentAction::TIMEZONE)->format('d/m/Y'),
            'start_time' => $start->setTimezone(CreateAppointmentAction::TIMEZONE)->format('H:i'),
            'end_time' => $end->setTimezone(CreateAppointmentAction::TIMEZONE)->format('H:i'),
            'visible_services' => $items->map(fn ($item) => [
                'name' => $item->service_name,
                'duration_minutes' => $item->duration_minutes,
                'quantity' => $item->quantity,
            ])->all(),
            ...($viewAll ? [
                'personnel' => $items->pluck('assignedTo.name')->filter()->unique()->values()->all(),
            ] : []),
            'visible_total' => $total,
            'deposit' => $this->deposit ? [
                'status' => $this->deposit->status,
                'status_label' => $this->depositStatusLabel($this->deposit->status),
                ...($viewAll ? [
                    'amount' => $this->deposit->amount,
                    'available_amount' => $this->deposit->availableAmount(),
                ] : []),
            ] : null,
            'linked_sale' => $canViewReceipt ? [
                'sale_number' => $this->sale->sale_number,
                'total' => $this->sale->total,
                'status' => $this->sale->status,
                'status_label' => $this->sale->status === 'canceled' ? 'Anulada' : 'Completada',
                'receipt_url' => route('sales.receipt', $this->sale),
            ] : null,
            'completed_at_display' => $this->completed_at?->setTimezone(CreateAppointmentAction::TIMEZONE)
                ->locale('es')
                ->translatedFormat('j \d\e F \d\e Y, g:i a'),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Appointment::STATUS_SCHEDULED => 'Programada',
            Appointment::STATUS_COMPLETED => 'Completada',
            Appointment::STATUS_CANCELED => 'Cancelada',
            Appointment::STATUS_NO_SHOW => 'No llegó',
            default => $status,
        };
    }

    private function depositStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente de aplicar',
            'applied' => 'Aplicado',
            'refunded' => 'Devuelto',
            'partially_refunded' => 'Devuelto parcialmente',
            'retained' => 'Retenido',
            default => $status,
        };
    }
}

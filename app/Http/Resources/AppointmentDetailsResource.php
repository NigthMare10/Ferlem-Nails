<?php

namespace App\Http\Resources;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Support\Money;
use App\Support\Permissions;
use App\Support\BusinessHours;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = (new AppointmentResource($this->resource))->toArray($request);
        $viewAll = $request->user()?->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL) ?? false;
        $canManageDeposit = $request->user()?->hasPermissionTo(Permissions::APPOINTMENTS_MANAGE_DEPOSIT) ?? false;
        $canResolveDeposit = $request->user()?->hasPermissionTo(Permissions::APPOINTMENTS_RESOLVE_DEPOSIT) ?? false;
        $canViewReceipt = $this->sale
            && ($request->user()?->hasRole('owner')
                || (($request->user()?->hasPermissionTo(Permissions::SALES_REPRINT) ?? false)
                    && ($request->user()?->hasPermissionTo(Permissions::SALES_VIEW_OWN) ?? false)
                    && $this->sale->sold_by === $request->user()?->getKey()));
        $canViewFinancials = $canManageDeposit || $canResolveDeposit;
        $visibleItemIds = collect($base['visible_items'])->pluck('id')->all();
        $terminalAt = $this->status === 'canceled' ? $this->canceled_at : $this->no_show_at;
        $terminalBy = $this->status === 'canceled' ? $this->canceledBy : $this->noShowBy;
        $terminalReason = $this->status === 'canceled' ? $this->cancellation_reason : $this->no_show_reason;
        $deposit = $this->deposit;
        $availableDepositCents = $deposit?->status === 'pending' ? $deposit->availableAmountCents() : 0;
        $visibleDepositCents = $viewAll
            ? $availableDepositCents
            : $this->visibleDepositCents($availableDepositCents, $visibleItemIds);
        $visibleExpectedCents = $viewAll
            ? Money::toCents($this->expected_total)
            : Money::toCents($base['visible_total']);
        $estimatedBalance = Money::fromCents($this->status === 'completed'
            ? 0
            : max(0, $visibleExpectedCents - $visibleDepositCents));

        return [
            ...$base,
            'date' => CarbonImmutable::parse($base['visible_start'])->setTimezone(CreateAppointmentAction::TIMEZONE)->format('Y-m-d'),
            'outside_business_hours' => ! BusinessHours::contains(
                $this->scheduled_start->setTimezone(CreateAppointmentAction::TIMEZONE),
                $this->scheduled_end->setTimezone(CreateAppointmentAction::TIMEZONE),
            ),
            'created_by' => $this->when($viewAll, [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'created_at' => $this->created_at->toIso8601String(),
            'created_at_display' => $this->created_at
                ->setTimezone(CreateAppointmentAction::TIMEZONE)
                ->locale('es')
                ->translatedFormat('j \d\e F \d\e Y, g:i a'),
            'status_reason' => $terminalReason,
            'status_changed_at' => $terminalAt?->toIso8601String(),
            'status_changed_at_display' => $terminalAt?->setTimezone(CreateAppointmentAction::TIMEZONE)
                ->locale('es')
                ->translatedFormat('j \d\e F \d\e Y, g:i a'),
            'status_changed_by' => $terminalBy ? [
                'id' => $terminalBy->id,
                'name' => $terminalBy->name,
            ] : ($this->status === 'no_show' && $this->no_show_reason === 'Marcada automáticamente al vencer el tiempo disponible para cobrar.' ? ['name' => 'Sistema'] : null),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'completed_at_display' => $this->completed_at ? $this->displayDateTime($this->completed_at) : null,
            'linked_sale' => $this->sale ? [
                'id' => $this->sale->id,
                'sale_number' => $this->sale->sale_number,
                'total' => $this->sale->total,
                'status' => $this->sale->status,
                'status_label' => $this->sale->status === 'canceled' ? 'Anulada' : 'Completada',
                'receipt_url' => route('sales.receipt', $this->sale),
                'can_view_receipt' => $canViewReceipt,
            ] : null,
            'can_manage_deposit' => $canManageDeposit,
            'can_resolve_deposit' => $canResolveDeposit,
            'deposit' => $deposit ? [
                'id' => $deposit->id,
                'amount' => $deposit->amount,
                'available_amount' => $this->when($canViewFinancials, $deposit->availableAmount()),
                'payment_method' => $deposit->payment_method,
                'payment_method_label' => $deposit->payment_method === 'card' ? 'Tarjeta' : 'Efectivo',
                'status' => $deposit->status,
                'status_label' => $this->depositStatus($deposit->status),
                'applied_amount' => $deposit->applied_amount,
                'refunded_amount' => $this->when($canViewFinancials, $deposit->refunded_amount),
                'retained_amount' => $this->when($canViewFinancials, $deposit->retained_amount),
                'estimated_balance' => $estimatedBalance,
                'paid_at' => $deposit->paid_at->toIso8601String(),
                'paid_at_display' => $this->displayDateTime($deposit->paid_at),
                'card_fee_rate' => $this->when($canViewFinancials, $deposit->card_fee_rate),
                'card_fee_amount' => $this->when($canViewFinancials, $deposit->card_fee_amount),
                'net_amount' => $this->when($canViewFinancials, $deposit->net_amount),
                'resolved_at' => $this->when($canViewFinancials, $deposit->resolved_at?->toIso8601String()),
                'resolved_at_display' => $this->when($canViewFinancials, $deposit->resolved_at ? $this->displayDateTime($deposit->resolved_at) : null),
                'resolved_by' => $this->when($canViewFinancials && $deposit->resolvedBy, $deposit->resolvedBy ? [
                    'id' => $deposit->resolvedBy->id,
                    'name' => $deposit->resolvedBy->name,
                ] : null),
                'resolution_notes' => $this->when($canViewFinancials, $deposit->resolution_notes),
            ] : null,
            'events' => $this->events->map(fn ($event) => [
                'id' => $event->id,
                'type' => $event->type,
                'type_label' => match ($event->type) {
                    'created' => 'Cita creada',
                    'updated' => 'Información actualizada',
                    'rescheduled' => 'Cita reprogramada',
                    'canceled' => 'Cita cancelada',
                    'no_show' => 'La clienta no llegó',
                    'deposit_recorded' => 'Adelanto registrado',
                    'deposit_resolved' => 'Adelanto resuelto',
                    'deposit_excess_refunded' => 'Excedente del adelanto devuelto',
                    'completed' => 'Cita atendida y cobrada',
                    'sale_canceled' => 'Venta anulada',
                    default => $event->type,
                },
                'changes' => $this->eventChanges($event->previous_values ?? [], $event->new_values ?? [], $viewAll, $visibleItemIds, $canViewFinancials),
                'performed_by' => $this->when($viewAll || in_array($event->type, ['canceled', 'no_show', 'sale_canceled'], true) || ($canViewFinancials && str_starts_with($event->type, 'deposit_')), $event->performedBy ? [
                    'id' => $event->performedBy->id,
                    'name' => $event->performedBy->name,
                ] : ['name' => 'Sistema']),
                'occurred_at' => $event->occurred_at->toIso8601String(),
                'occurred_at_display' => $event->occurred_at
                    ->setTimezone(CreateAppointmentAction::TIMEZONE)
                    ->locale('es')
                    ->translatedFormat('j \d\e F \d\e Y, g:i a'),
                'notes' => $this->when($viewAll || in_array($event->type, ['canceled', 'no_show', 'sale_canceled'], true) || ($canViewFinancials && str_starts_with($event->type, 'deposit_')), $event->notes),
            ])->values(),
        ];
    }

    private function eventChanges(array $previous, array $new, bool $viewAll, array $visibleItemIds, bool $canViewFinancials): array
    {
        $changes = [];

        if (array_key_exists('status', $new)) {
            $changes[] = $this->change('Estado', $this->status($previous['status'] ?? null), $this->status($new['status']));
        }

        foreach ([
            'deposit_amount' => 'Adelanto recibido',
            'deposit_refunded_amount' => 'Devuelto',
            'deposit_retained_amount' => 'Retenido',
            'deposit_available_amount' => 'Saldo disponible',
        ] as $key => $label) {
            if (array_key_exists($key, $new)
                && ($key === 'deposit_amount' || $canViewFinancials)) {
                $changes[] = $this->change($label, $this->money($previous[$key] ?? null), $this->money($new[$key]));
            }
        }
        if (array_key_exists('deposit_payment_method', $new)) {
            $changes[] = $this->change('Método del adelanto', '', $new['deposit_payment_method'] === 'card' ? 'Tarjeta' : 'Efectivo');
        }
        if (array_key_exists('deposit_status', $new)) {
            $changes[] = $this->change(
                'Estado del adelanto',
                isset($previous['deposit_status']) ? $this->depositStatus($previous['deposit_status']) : '',
                $this->depositStatus($new['deposit_status']),
            );
        }

        if (array_key_exists('items', $new)) {
            foreach ($new['items'] as $index => $item) {
                if (! $viewAll && ! in_array($item['id'] ?? null, $visibleItemIds, true)) {
                    continue;
                }
                $old = $previous['items'][$index] ?? null;
                if (! $old) {
                    $changes[] = $this->change($item['service_name'], '', sprintf('%s · %d min reservados (habitual %d min)', $item['assigned_to']['name'] ?? 'No disponible', $item['duration_minutes'], $item['default_duration_minutes']));

                    continue;
                }

                if (($old['assigned_to']['name'] ?? null) !== ($item['assigned_to']['name'] ?? null)) {
                    $changes[] = $this->change($item['service_name'], $old['assigned_to']['name'] ?? 'No disponible', $item['assigned_to']['name'] ?? 'No disponible');
                }
            }
        }

        foreach ([
            'client_name' => 'Nombre',
            'client_phone' => 'Teléfono',
            'notes' => 'Notas',
        ] as $key => $label) {
            if (array_key_exists($key, $new)) {
                $changes[] = $this->change($label, $this->text($previous[$key] ?? null), $this->text($new[$key]));
            }
        }

        if ($viewAll && array_key_exists('assigned_to', $new)) {
            $changes[] = $this->change('Persona asignada', $this->person($previous['assigned_to'] ?? null), $this->person($new['assigned_to']));
        }

        if ($viewAll && array_key_exists('scheduled_start', $new)) {
            $oldStart = $this->dateTime($previous['scheduled_start'] ?? null);
            $newStart = $this->dateTime($new['scheduled_start']);
            if ($oldStart && $newStart) {
                if ($oldStart->toDateString() !== $newStart->toDateString()) {
                    $changes[] = $this->change('Fecha', $this->date($oldStart), $this->date($newStart));
                }
                if ($oldStart->format('H:i') !== $newStart->format('H:i')) {
                    $changes[] = $this->change('Hora', $this->time($oldStart), $this->time($newStart));
                }
            }
        }

        if ($viewAll && array_key_exists('scheduled_end', $new)) {
            $oldEnd = $this->dateTime($previous['scheduled_end'] ?? null);
            $newEnd = $this->dateTime($new['scheduled_end']);
            if ($oldEnd && $newEnd) {
                $changes[] = $this->change('Finalización', $this->time($oldEnd), $this->time($newEnd));
            }
        }

        return $changes;
    }

    private function visibleDepositCents(int $depositCents, array $visibleItemIds): int
    {
        $items = $this->items->sortBy('position')->values();
        $totalCents = $items->sum(fn ($item) => Money::toCents($item->line_total));
        if ($depositCents === 0 || $totalCents === 0) {
            return 0;
        }

        $visible = 0;
        $used = 0;
        $last = $items->count() - 1;
        foreach ($items as $index => $item) {
            $lineCents = Money::toCents($item->line_total);
            $allocated = $index === $last
                ? $depositCents - $used
                : intdiv(($depositCents * $lineCents) + intdiv($totalCents, 2), $totalCents);
            $allocated = min($lineCents, $allocated, $depositCents - $used);
            if (in_array($item->getKey(), $visibleItemIds, true)) {
                $visible += $allocated;
            }
            $used += $allocated;
        }

        return $visible;
    }

    private function change(string $label, string $previous, string $new): array
    {
        return compact('label', 'previous', 'new');
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : 'Sin registrar';
    }

    private function status(mixed $value): string
    {
        return match ($value) {
            'scheduled' => 'Programada',
            'completed' => 'Completada',
            'canceled' => 'Cancelada',
            'no_show' => 'No llegó',
            default => 'No disponible',
        };
    }

    private function depositStatus(mixed $value): string
    {
        return match ($value) {
            'pending' => 'Pendiente de aplicar',
            'applied' => 'Aplicado',
            'refunded' => 'Devuelto',
            'partially_refunded' => 'Devuelto parcialmente',
            'retained' => 'Retenido',
            default => 'No disponible',
        };
    }

    private function money(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $cents = Money::toCents($value);

        return 'L '.number_format(intdiv($cents, 100), 0, '.', ',').'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function displayDateTime($value): string
    {
        return $value->setTimezone(CreateAppointmentAction::TIMEZONE)
            ->locale('es')
            ->translatedFormat('j \d\e F \d\e Y, g:i a');
    }

    private function person(mixed $value): string
    {
        if (is_array($value) && is_string($value['name'] ?? null)) {
            return $value['name'];
        }

        return 'No disponible';
    }

    private function dateTime(mixed $value): ?CarbonImmutable
    {
        return is_string($value) ? CarbonImmutable::parse($value)->setTimezone(CreateAppointmentAction::TIMEZONE) : null;
    }

    private function date(CarbonImmutable $date): string
    {
        return $date->locale('es')->translatedFormat('j \d\e F \d\e Y');
    }

    private function time(CarbonImmutable $date): string
    {
        return $date->locale('es')->translatedFormat('g:i a');
    }
}

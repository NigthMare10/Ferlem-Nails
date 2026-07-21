<?php

namespace App\Http\Resources;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = (new AppointmentResource($this->resource))->toArray($request);
        $viewAll = $request->user()?->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL) ?? false;
        $visibleItemIds = collect($base['visible_items'])->pluck('id')->all();
        $terminalAt = $this->status === 'canceled' ? $this->canceled_at : $this->no_show_at;
        $terminalBy = $this->status === 'canceled' ? $this->canceledBy : $this->noShowBy;
        $terminalReason = $this->status === 'canceled' ? $this->cancellation_reason : $this->no_show_reason;

        return [
            ...$base,
            'date' => CarbonImmutable::parse($base['visible_start'])->setTimezone(CreateAppointmentAction::TIMEZONE)->format('Y-m-d'),
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
                    default => $event->type,
                },
                'changes' => $this->eventChanges($event->previous_values ?? [], $event->new_values ?? [], $viewAll, $visibleItemIds),
                'performed_by' => $this->when($viewAll || in_array($event->type, ['canceled', 'no_show'], true), [
                    'id' => $event->performedBy->id,
                    'name' => $event->performedBy->name,
                ]),
                'occurred_at' => $event->occurred_at->toIso8601String(),
                'occurred_at_display' => $event->occurred_at
                    ->setTimezone(CreateAppointmentAction::TIMEZONE)
                    ->locale('es')
                    ->translatedFormat('j \d\e F \d\e Y, g:i a'),
                'notes' => $this->when($viewAll || in_array($event->type, ['canceled', 'no_show'], true), $event->notes),
            ])->values(),
        ];
    }

    private function eventChanges(array $previous, array $new, bool $viewAll, array $visibleItemIds): array
    {
        $changes = [];

        if (array_key_exists('status', $new)) {
            $changes[] = $this->change('Estado', $this->status($previous['status'] ?? null), $this->status($new['status']));
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

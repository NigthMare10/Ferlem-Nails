<?php

namespace App\Http\Resources;

use App\Models\Expense;
use App\Models\ExpenseEvent;
use App\Support\ExpenseAudit;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...(new ExpenseListResource($this->resource))->resolve($request),
            'notes' => $this->notes,
            'created_at_display' => $this->created_at?->setTimezone('America/Tegucigalpa')->translatedFormat('d/m/Y, h:i a'),
            'attachment' => $this->attachment_path ? [
                'original_name' => $this->attachment_original_name,
                'mime' => $this->attachment_mime,
                'size' => $this->attachment_size,
                'uploaded_at_display' => $this->attachment_uploaded_at?->setTimezone('America/Tegucigalpa')->translatedFormat('d/m/Y, h:i a'),
            ] : null,
            'cancellation' => $this->status === Expense::STATUS_CANCELED ? [
                'canceled_at_display' => $this->canceled_at?->setTimezone('America/Tegucigalpa')->translatedFormat('d/m/Y, h:i a'),
                'canceled_by' => $this->canceledBy?->name,
                'reason' => $this->cancellation_reason,
            ] : null,
            'events' => $this->events->map(fn (ExpenseEvent $event) => [
                'id' => $event->id,
                'type' => $event->type,
                'type_label' => match ($event->type) {
                    ExpenseEvent::TYPE_UPDATED => 'Gasto modificado',
                    ExpenseEvent::TYPE_CANCELED => 'Gasto anulado',
                    default => 'Gasto registrado',
                },
                'performed_by' => $event->performedBy->name,
                'occurred_at_display' => $event->occurred_at?->setTimezone('America/Tegucigalpa')->translatedFormat('d/m/Y, h:i a'),
                'notes' => $event->notes,
                'changes' => $this->changes($event),
            ])->values(),
            'attachment_url' => $this->attachment_path && $request->user()->can(Permissions::EXPENSES_VIEW_ATTACHMENT)
                ? route('expenses.attachment', $this->resource)
                : null,
        ];
    }

    private function changes(ExpenseEvent $event): array
    {
        if ($event->type !== ExpenseEvent::TYPE_UPDATED) {
            return [];
        }

        $previous = $event->previous_values ?? [];
        $current = $event->new_values ?? [];

        return collect(ExpenseAudit::labels())
            ->filter(fn (string $label, string $key) => ($previous[$key] ?? null) !== ($current[$key] ?? null))
            ->map(fn (string $label, string $key) => [
                'field' => $label,
                'previous' => $this->displayValue($key, $previous[$key] ?? null),
                'current' => $this->displayValue($key, $current[$key] ?? null),
            ])
            ->values()
            ->all();
    }

    private function displayValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Sin dato';
        }
        if ($key === 'payment_method') {
            return match ($value) {
                Expense::PAYMENT_METHOD_CARD => 'Tarjeta',
                Expense::PAYMENT_METHOD_TRANSFER => 'Transferencia',
                default => 'Efectivo',
            };
        }
        if ($key === 'amount') {
            return 'L '.Money::fromCents(Money::toCents((string) $value));
        }
        if ($key === 'employee' && is_array($value)) {
            return (string) ($value['name'] ?? 'Sin dato');
        }

        return (string) $value;
    }
}

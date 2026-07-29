<?php

namespace App\Http\Resources;

use App\Models\Sale;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_number' => $this->sale_number,
            'sold_at' => $this->sold_at?->toISOString(),
            'sold_at_display' => $this->sold_at
                ?->setTimezone('America/Tegucigalpa')
                ->translatedFormat('d/m/Y, h:i a'),
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'total' => $this->total,
            'total_services' => $this->total_services,
            'status' => $this->status,
            'is_canceled' => $this->status === Sale::STATUS_CANCELED,
            'cancellation' => $this->status === Sale::STATUS_CANCELED ? [
                'canceled_at' => $this->canceled_at?->toISOString(),
                'canceled_at_display' => $this->canceled_at?->setTimezone('America/Tegucigalpa')->translatedFormat('d/m/Y, h:i a'),
                'canceled_by' => $this->canceledBy ? ['id' => $this->canceledBy->id, 'name' => $this->canceledBy->name] : null,
                'reason' => $this->cancellation_reason,
            ] : null,
            'can_cancel' => $this->status === Sale::STATUS_COMPLETED
                && $request->user()?->is_active
                && $request->user()?->can(Permissions::SALES_CANCEL),
            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->methodLabel($this->payment_method),
            'client' => $this->client_name ? [
                'name' => $this->client_name,
                'phone' => $this->appointment?->client_phone,
            ] : null,
            'sold_by' => [
                'id' => $this->soldBy->id,
                'name' => $this->soldBy->name,
            ],
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'service_name' => $item->service_name,
                'service_description' => $item->service_description,
                'duration_minutes' => $item->duration_minutes,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
                'performed_by' => $item->performedBy ? [
                    'id' => $item->performedBy->id,
                    'name' => $item->performedBy->name,
                ] : null,
            ])->values(),
            'additional_charges' => $this->additionalCharges->map(fn ($charge) => [
                'name' => $charge->name ?: $charge->description ?: 'Cargo adicional',
                'amount' => $charge->amount,
            ])->values(),
            'payments' => $this->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'type' => $payment->type,
                'type_label' => $payment->type === 'deposit_applied' ? 'Adelanto aplicado' : 'Saldo final pagado',
                'method' => $payment->method,
                'method_label' => $this->methodLabel($payment->method),
                'amount' => $payment->amount,
                'proof_url' => $payment->method === Sale::PAYMENT_METHOD_TRANSFER
                    && $payment->proof_path
                    && $request->user()?->can(Permissions::SALES_VIEW_TRANSFER_PROOF)
                        ? route('sales.payments.proof', [$this->resource, $payment])
                        : null,
            ])->values(),
        ];
    }

    private function methodLabel(string $method): string
    {
        return match ($method) {
            Sale::PAYMENT_METHOD_CARD => 'Tarjeta',
            Sale::PAYMENT_METHOD_TRANSFER => 'Transferencia',
            default => 'Efectivo',
        };
    }
}

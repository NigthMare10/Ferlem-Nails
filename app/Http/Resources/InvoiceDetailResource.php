<?php

namespace App\Http\Resources;

use App\Models\Sale;
use App\Support\Permissions;
use App\Support\SaleAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $methods = $this->payments->pluck('method')->unique();

        return [
            'id' => $this->id,
            'sale_number' => $this->sale_number,
            'status' => $this->status,
            'status_label' => $this->status === Sale::STATUS_CANCELED ? 'Anulada' : 'Completada',
            'client_name' => $this->client_name ?: 'Sin nombre',
            'sold_at_display' => $this->sold_at?->setTimezone('America/Tegucigalpa')->translatedFormat('d/m/Y, h:i a'),
            'sold_by' => ['name' => $this->soldBy->name],
            'payment_method_label' => $methods->count() > 1 ? 'Mixto' : $this->methodLabel((string) $methods->first()),
            'total' => $this->total,
            'total_services' => $this->total_services,
            'receipt_url' => route('sales.receipt', $this->resource),
            'related_appointment' => $this->appointment_id ? [
                'label' => 'Cita vinculada',
                'url' => route('appointments.index', ['appointment' => $this->appointment_id]),
            ] : null,
            'items' => $this->items->map(fn ($item) => [
                'service_name' => $item->service_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
                'performed_by' => $item->performedBy?->name,
            ])->values(),
            'payments' => $this->payments->map(function ($payment) use ($request) {
                $canViewProof = $payment->proof_path
                    && $request->user()->can(Permissions::SALES_VIEW_TRANSFER_PROOF)
                    && SaleAccess::canView($request->user(), $this->resource);

                return [
                    'id' => $payment->id,
                    'type' => $payment->type,
                    'type_label' => $payment->type === 'deposit_applied' ? 'Adelanto aplicado' : 'Saldo final',
                    'method' => $payment->method,
                    'method_label' => $this->methodLabel($payment->method),
                    'amount' => $payment->amount,
                    'proof_status_label' => $payment->method !== Sale::PAYMENT_METHOD_TRANSFER
                        ? 'No aplica'
                        : ($payment->proof_path ? 'Con captura' : 'Pendiente de captura'),
                    'proof_url' => $canViewProof
                        ? route('invoices.payments.proof.show', [$this->resource, $payment])
                        : null,
                    'can_upload_proof' => SaleAccess::canUploadProof($request->user(), $this->resource, $payment),
                ];
            })->values(),
            'cancellation' => $this->status === Sale::STATUS_CANCELED ? [
                'canceled_at_display' => $this->canceled_at?->setTimezone('America/Tegucigalpa')->translatedFormat('d/m/Y, h:i a'),
                'canceled_by' => $this->canceledBy?->name,
                'reason' => $this->cancellation_reason,
            ] : null,
            'can_cancel' => $this->status === Sale::STATUS_COMPLETED
                && $request->user()->can(Permissions::SALES_CANCEL),
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

<?php

namespace App\Http\Resources;

use App\Models\Sale;
use App\Support\Permissions;
use App\Support\SaleAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $methods = $this->payments->pluck('method')->unique()->values();
        $transfer = $this->payments->firstWhere('method', Sale::PAYMENT_METHOD_TRANSFER);
        $canViewProof = $transfer?->proof_path
            && $request->user()->can(Permissions::SALES_VIEW_TRANSFER_PROOF)
            && SaleAccess::canView($request->user(), $this->resource);

        return [
            'id' => $this->id,
            'sale_number' => $this->sale_number,
            'client_name' => $this->client_name ?: 'Sin nombre',
            'sold_at_display' => $this->sold_at?->setTimezone('America/Tegucigalpa')->translatedFormat('d/m/Y, h:i a'),
            'sold_by' => ['id' => $this->soldBy->id, 'name' => $this->soldBy->name],
            'total' => $this->total,
            'status' => $this->status,
            'status_label' => $this->status === Sale::STATUS_CANCELED ? 'Anulada' : 'Completada',
            'payment_method_label' => $methods->count() > 1 ? 'Mixto' : $this->methodLabel((string) $methods->first()),
            'proof_status' => ! $transfer
                ? 'not_applicable'
                : ($transfer->proof_path ? 'with_proof' : 'pending'),
            'proof_status_label' => ! $transfer
                ? 'No aplica'
                : ($transfer->proof_path ? 'Con captura' : 'Sin captura'),
            'show_url' => route('invoices.show', $this->resource),
            'receipt_url' => route('sales.receipt', $this->resource),
            'can_cancel' => $this->status === Sale::STATUS_COMPLETED
                && $request->user()->can(Permissions::SALES_CANCEL),
            'transfer_payment' => $transfer ? [
                'id' => $transfer->id,
                'amount' => $transfer->amount,
                'proof_url' => $canViewProof
                    ? route('invoices.payments.proof.show', [$this->resource, $transfer])
                    : null,
                'can_upload_proof' => SaleAccess::canUploadProof($request->user(), $this->resource, $transfer),
            ] : null,
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

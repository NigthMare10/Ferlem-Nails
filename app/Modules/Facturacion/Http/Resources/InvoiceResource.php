<?php

namespace App\Modules\Facturacion\Http\Resources;

use App\Modules\Shared\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'number' => $this->number,
            'status' => $this->status,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'cliente' => $this->cliente?->name,
            'total_amount' => $this->total_amount,
            'total_formatted' => Money::format((int) $this->total_amount),
            'items' => $this->detalles->map(fn ($detail) => [
                'description' => $detail->description,
                'quantity' => $detail->quantity,
                'total_formatted' => Money::format((int) $detail->total_amount),
            ])->all(),
        ];
    }
}

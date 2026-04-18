<?php

namespace App\Modules\VentasPOS\Http\Resources;

use App\Modules\Shared\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'status' => $this->status,
            'currency_code' => $this->currency_code,
            'cliente' => [
                'public_id' => $this->cliente?->public_id,
                'name' => $this->cliente?->name,
            ],
            'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'total_formatted' => Money::format((int) $this->total_amount),
            'items' => $this->detalles->map(fn ($detail) => [
                'description' => $detail->description,
                'quantity' => $detail->quantity,
                'unit_price_amount' => $detail->unit_price_amount,
                'unit_price_formatted' => Money::format((int) $detail->unit_price_amount),
                'total_amount' => $detail->total_amount,
                'total_formatted' => Money::format((int) $detail->total_amount),
            ])->all(),
        ];
    }
}

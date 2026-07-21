<?php

namespace App\Http\Resources;

use App\Models\Sale;
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
            'total' => $this->total,
            'total_services' => $this->total_services,
            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->payment_method === Sale::PAYMENT_METHOD_CARD ? 'Tarjeta' : 'Efectivo',
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
            ])->values(),
        ];
    }
}

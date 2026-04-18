<?php

namespace App\Modules\Catalogo\Http\Resources;

use App\Modules\Shared\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $precio = $this->resolved_price ?? $this->base_price_amount;

        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'description' => $this->description,
            'duration_minutes' => $this->duration_minutes,
            'category' => $this->categoria?->name,
            'price_amount' => $precio,
            'price_formatted' => Money::format((int) $precio),
            'allow_manual_price' => $this->allow_manual_price,
        ];
    }
}

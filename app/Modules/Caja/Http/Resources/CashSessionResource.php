<?php

namespace App\Modules\Caja\Http\Resources;

use App\Modules\Shared\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'status' => $this->status,
            'opening_amount' => $this->opening_amount,
            'opening_amount_formatted' => Money::format((int) $this->opening_amount),
            'expected_amount' => $this->expected_amount,
            'expected_amount_formatted' => Money::format((int) $this->expected_amount),
            'counted_amount' => $this->counted_amount,
            'counted_amount_formatted' => $this->counted_amount !== null ? Money::format((int) $this->counted_amount) : null,
            'difference_amount' => $this->difference_amount,
            'difference_amount_formatted' => $this->difference_amount !== null ? Money::format((int) $this->difference_amount) : null,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
        ];
    }
}

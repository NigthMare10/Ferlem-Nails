<?php

namespace App\Modules\Clientes\Http\Resources;

use App\Modules\Shared\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->perfiles->first();

        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'rtn' => $this->rtn,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'perfil' => $profile ? [
                'public_id' => $profile->public_id,
                'alias' => $profile->alias,
                'alertas' => $profile->alertas,
                'preferencias' => $profile->preferencias,
                'saldo_a_favor' => $profile->saldo_a_favor,
                'saldo_a_favor_formatted' => Money::format((int) $profile->saldo_a_favor),
            ] : null,
        ];
    }
}

<?php

namespace App\Modules\Sucursales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->name,
            'city' => $this->city,
            'currency_code' => $this->currency_code,
            'configuracion' => $this->configuracion ? [
                'currency_symbol' => $this->configuracion->currency_symbol,
                'impuesto_nombre' => $this->configuracion->impuesto_nombre,
                'impuesto_porcentaje' => $this->configuracion->impuesto_porcentaje,
            ] : null,
        ];
    }
}

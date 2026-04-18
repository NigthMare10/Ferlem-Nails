<?php

namespace App\Modules\Agenda\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'status' => $this->status,
            'scheduled_start' => $this->scheduled_start?->toIso8601String(),
            'scheduled_end' => $this->scheduled_end?->toIso8601String(),
            'cliente' => $this->cliente?->name,
            'servicio' => $this->servicio?->name,
            'empleado' => $this->empleado?->name,
            'notes' => $this->notes,
        ];
    }
}

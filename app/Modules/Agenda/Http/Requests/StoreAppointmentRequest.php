<?php

namespace App\Modules\Agenda\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('agenda.crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'cliente_public_id' => ['required', 'exists:clientes,public_id'],
            'servicio_public_id' => ['required', 'exists:servicios,public_id'],
            'empleado_public_id' => ['nullable', 'exists:empleados,public_id'],
            'scheduled_start' => ['required', 'date'],
            'scheduled_end' => ['required', 'date', 'after:scheduled_start'],
            'status' => ['nullable', 'in:programada,confirmada,cancelada,completada,no_asistio'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

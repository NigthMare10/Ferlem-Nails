<?php

namespace App\Modules\VentasPOS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pos.usar') ?? false;
    }

    public function rules(): array
    {
        return [
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.servicio_public_id' => ['required', 'exists:servicios,public_id'],
            'items.*.empleado_public_id' => ['nullable', 'exists:empleados,public_id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class CheckoutAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->is_active
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS)
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_CONVERT_TO_SALE)
            && $user->hasPermissionTo(Permissions::SALES_ACCESS)
            && $user->hasPermissionTo(Permissions::SALES_CREATE);
    }

    protected function prepareForValidation(): void
    {
        foreach (['checkout_token', 'payment_method'] as $field) {
            if (is_string($this->{$field})) {
                $this->merge([$field => strtolower(trim($this->{$field}))]);
            }
        }

        // Empty arrays are omitted by multipart FormData, but an empty removal
        // list is a valid explicit confirmation that every reserved item remains.
        if (! $this->has('removed_appointment_item_ids')) {
            $this->merge(['removed_appointment_item_ids' => []]);
        }
    }

    public function rules(): array
    {
        return [
            'checkout_token' => ['required', 'uuid'],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'transfer'])],
            'payment_proof' => [
                'nullable',
                'prohibited_unless:payment_method,transfer',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024),
            ],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.appointment_item_id' => ['nullable', 'integer'],
            'items.*.service_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.performed_by' => ['required', 'integer'],
            'removed_appointment_item_ids' => ['present', 'array'],
            'removed_appointment_item_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'checkout_token.required' => 'No se pudo identificar esta confirmación. Recarga la página e inténtalo nuevamente.',
            'checkout_token.uuid' => 'La confirmación de la venta no es válida.',
            'payment_method.required' => 'Selecciona el método para pagar el saldo final.',
            'payment_method.in' => 'El método del saldo final debe ser efectivo, tarjeta o transferencia.',
            'payment_proof.prohibited_unless' => 'La captura solo puede adjuntarse a una transferencia.',
            'payment_proof.mimes' => 'La captura debe ser JPG, PNG o WEBP.',
            'payment_proof.max' => 'La captura no puede superar 5 MB.',
            'items.required' => 'Agrega al menos un servicio realizado.',
            'items.min' => 'Agrega al menos un servicio realizado.',
            'items.*.quantity.min' => 'La cantidad mínima es 1.',
            'items.*.quantity.max' => 'La cantidad máxima por línea es 50.',
            'items.*.performed_by.required' => 'Selecciona quién realizó cada servicio.',
            'removed_appointment_item_ids.present' => 'Confirma explícitamente cualquier servicio reservado que no se realizó.',
        ];
    }
}

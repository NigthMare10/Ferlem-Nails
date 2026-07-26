<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class CreateSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active
            && $this->user()->can(Permissions::SALES_ACCESS)
            && $this->user()->can(Permissions::SALES_CREATE);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->checkout_token)) {
            $this->merge(['checkout_token' => strtolower(trim($this->checkout_token))]);
        }

        if (is_string($this->payment_method)) {
            $this->merge(['payment_method' => strtolower(trim($this->payment_method))]);
        }
        if (is_string($this->client_name)) {
            $name = trim($this->client_name);
            $this->merge(['client_name' => $name === '' ? null : $name]);
        }
    }

    public function rules(): array
    {
        return [
            'checkout_token' => ['required', 'uuid'],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'transfer'])],
            'client_name' => ['nullable', 'string', 'max:120'],
            'payment_proof' => [
                'nullable',
                'prohibited_unless:payment_method,transfer',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024),
            ],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.service_id' => ['required', 'integer', 'exists:services,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'checkout_token.required' => 'No se pudo identificar esta confirmación. Recarga la página e inténtalo nuevamente.',
            'checkout_token.uuid' => 'La confirmación de la venta no es válida.',
            'payment_method.required' => 'Selecciona el método de pago.',
            'payment_method.in' => 'El método de pago debe ser efectivo, tarjeta o transferencia.',
            'client_name.max' => 'El nombre de la clienta no puede superar 120 caracteres.',
            'payment_proof.prohibited_unless' => 'La captura solo puede adjuntarse a una transferencia.',
            'payment_proof.mimes' => 'La captura debe ser JPG, PNG o WEBP.',
            'payment_proof.max' => 'La captura no puede superar 5 MB.',
            'items.required' => 'Agrega al menos un servicio.',
            'items.array' => 'La selección de servicios no es válida.',
            'items.min' => 'Agrega al menos un servicio.',
            'items.max' => 'La venta contiene demasiadas líneas de servicios.',
            'items.*.service_id.required' => 'Selecciona un servicio válido.',
            'items.*.service_id.integer' => 'El servicio seleccionado no es válido.',
            'items.*.service_id.exists' => 'Uno de los servicios seleccionados ya no existe.',
            'items.*.quantity.required' => 'Indica la cantidad del servicio.',
            'items.*.quantity.integer' => 'La cantidad debe ser un número entero.',
            'items.*.quantity.min' => 'La cantidad mínima es 1.',
            'items.*.quantity.max' => 'La cantidad máxima por servicio es 50.',
        ];
    }
}

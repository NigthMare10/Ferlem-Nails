<?php

namespace App\Modules\Pagos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pagos.registrar') ?? false;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'in:efectivo,tarjeta_manual,transferencia'],
            'amount' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}

<?php

namespace App\Modules\Caja\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('caja.cerrar') ?? false;
    }

    public function rules(): array
    {
        return [
            'counted_amount' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

<?php

namespace App\Modules\Caja\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('caja.abrir') ?? false;
    }

    public function rules(): array
    {
        return [
            'opening_amount' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

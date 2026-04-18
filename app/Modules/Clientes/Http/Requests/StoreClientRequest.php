<?php

namespace App\Modules\Clientes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clientes.crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'rtn' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'alias' => ['nullable', 'string', 'max:255'],
            'alertas' => ['nullable', 'string'],
            'preferencias' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

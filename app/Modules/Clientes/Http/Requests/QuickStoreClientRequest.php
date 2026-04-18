<?php

namespace App\Modules\Clientes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuickStoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pos.usar') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'alias' => ['nullable', 'string', 'max:255'],
        ];
    }
}

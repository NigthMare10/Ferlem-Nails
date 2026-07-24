<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.update');
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role' => ['nullable', Rule::in(['owner', 'administrator', 'employee'])]];
    }
}

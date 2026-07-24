<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.reset_password');
    }

    public function rules(): array
    {
        return ['password' => ['required', 'confirmed', Password::min(8)]];
    }
}

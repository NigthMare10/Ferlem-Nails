<?php

namespace App\Modules\IdentidadAcceso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReauthenticateAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}

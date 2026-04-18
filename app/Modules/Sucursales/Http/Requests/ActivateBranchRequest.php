<?php

namespace App\Modules\Sucursales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sucursales.seleccionar') ?? false;
    }

    public function rules(): array
    {
        return [
            'sucursal_public_id' => ['required', 'exists:sucursales,public_id'],
        ];
    }
}

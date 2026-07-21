<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class MarkAppointmentNoShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->is_active
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS)
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_MARK_NO_SHOW);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => is_string($this->reason) ? trim($this->reason) : $this->reason]);
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:500']];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Escribe el motivo por el que la clienta no llegó.',
            'reason.min' => 'El motivo debe tener al menos 5 caracteres.',
            'reason.max' => 'El motivo no puede superar 500 caracteres.',
        ];
    }
}

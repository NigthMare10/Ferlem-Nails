<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->is_active
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS)
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_UPDATE);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_name' => is_string($this->client_name) ? trim($this->client_name) : $this->client_name,
            'client_phone' => is_string($this->client_phone) && trim($this->client_phone) !== '' ? trim($this->client_phone) : null,
            'notes' => is_string($this->notes) && trim($this->notes) !== '' ? trim($this->notes) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'client_name' => ['required', 'string', 'max:120'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_name.required' => 'Escribe el nombre de la clienta.',
            'client_name.max' => 'El nombre de la clienta no puede superar 120 caracteres.',
            'client_phone.max' => 'El teléfono no puede superar 30 caracteres.',
            'notes.max' => 'Las notas no pueden superar 1000 caracteres.',
        ];
    }
}

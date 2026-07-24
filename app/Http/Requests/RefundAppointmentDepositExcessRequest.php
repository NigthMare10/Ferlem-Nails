<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class RefundAppointmentDepositExcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->is_active
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS)
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_RESOLVE_DEPOSIT);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => is_string($this->amount) ? trim($this->amount) : $this->amount,
            'operation_token' => is_string($this->operation_token) ? strtolower(trim($this->operation_token)) : $this->operation_token,
            'note' => is_string($this->note) && trim($this->note) !== '' ? trim($this->note) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'regex:/^(\d{1,10})(?:\.(\d{1,2}))?$/'],
            'operation_token' => ['required', 'uuid'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Indica el excedente exacto que se devolverá.',
            'amount.regex' => 'El excedente debe ser un monto válido con máximo dos decimales.',
            'operation_token.required' => 'No se pudo identificar esta devolución. Inténtalo nuevamente.',
            'operation_token.uuid' => 'La identificación de la devolución no es válida.',
            'note.max' => 'La nota no puede superar 500 caracteres.',
        ];
    }
}

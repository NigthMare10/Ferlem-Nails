<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->is_active
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS)
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_MANAGE_DEPOSIT);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['note' => is_string($this->note) && trim($this->note) !== '' ? trim($this->note) : null]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'regex:/^(\d{1,10})(?:\.(\d{1,2}))?$/'],
            'payment_method' => ['required', Rule::in(['cash', 'card'])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Escribe el monto del adelanto.',
            'amount.regex' => 'El monto del adelanto debe ser válido y usar máximo dos decimales.',
            'payment_method.required' => 'Selecciona el método de pago.',
            'payment_method.in' => 'El método de pago no es válido.',
            'note.max' => 'La nota no puede superar 500 caracteres.',
        ];
    }
}

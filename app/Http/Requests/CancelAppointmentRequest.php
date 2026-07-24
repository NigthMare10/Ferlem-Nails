<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        $canCancel = (bool) $user?->is_active
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS)
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_CANCEL);
        $requiresResolution = $this->filled('deposit_resolution')
            || $this->route('appointment')?->deposit()->where('status', 'pending')->exists();

        return $canCancel
            && (! $requiresResolution || $user->hasPermissionTo(Permissions::APPOINTMENTS_RESOLVE_DEPOSIT));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => is_string($this->reason) ? trim($this->reason) : $this->reason,
            'resolution_notes' => is_string($this->resolution_notes) && trim($this->resolution_notes) !== '' ? trim($this->resolution_notes) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'deposit_resolution' => ['nullable', Rule::in(['full_refund', 'full_retention', 'partial_refund'])],
            'refund_amount' => [Rule::requiredIf($this->input('deposit_resolution') === 'partial_refund'), 'nullable', 'regex:/^(\d{1,10})(?:\.(\d{1,2}))?$/'],
            'operation_token' => [Rule::requiredIf(in_array($this->input('deposit_resolution'), ['full_refund', 'partial_refund'], true)), 'nullable', 'uuid'],
            'resolution_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Escribe el motivo de la cancelación.',
            'reason.min' => 'El motivo debe tener al menos 5 caracteres.',
            'reason.max' => 'El motivo no puede superar 500 caracteres.',
            'deposit_resolution.in' => 'La resolución del adelanto no es válida.',
            'refund_amount.required' => 'Escribe el monto que se devolverá.',
            'refund_amount.regex' => 'El monto de devolución debe ser válido y usar máximo dos decimales.',
            'operation_token.required' => 'No se pudo identificar esta devolución. Inténtalo nuevamente.',
            'operation_token.uuid' => 'La identificación de la devolución no es válida.',
            'resolution_notes.max' => 'La nota de resolución no puede superar 500 caracteres.',
        ];
    }
}

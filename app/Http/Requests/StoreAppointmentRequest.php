<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        $canCreate = (bool) $user?->is_active
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS)
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_CREATE);

        return $canCreate
            && (! $this->boolean('has_deposit') || $user->hasPermissionTo(Permissions::APPOINTMENTS_MANAGE_DEPOSIT));
    }

    protected function prepareForValidation(): void
    {
        $deposit = $this->input('deposit');
        if (is_array($deposit)) {
            $deposit['note'] = is_string($deposit['note'] ?? null) && trim($deposit['note']) !== ''
                ? trim($deposit['note'])
                : null;
        }

        $this->merge([
            'client_name' => is_string($this->client_name) ? trim($this->client_name) : $this->client_name,
            'client_phone' => is_string($this->client_phone) && trim($this->client_phone) !== '' ? trim($this->client_phone) : null,
            'notes' => is_string($this->notes) && trim($this->notes) !== '' ? trim($this->notes) : null,
            'deposit' => $deposit,
        ]);
    }

    public function rules(): array
    {
        return [
            'client_name' => ['required', 'string', 'max:120'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.service_id' => ['required', 'integer', 'exists:services,id'],
            'items.*.assigned_to' => ['required', 'integer', 'exists:users,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.duration_minutes' => ['required', 'integer', 'min:5', 'max:480', 'multiple_of:5'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'has_deposit' => ['sometimes', 'boolean'],
            'deposit' => [Rule::requiredIf($this->boolean('has_deposit')), 'nullable', 'array'],
            'deposit.amount' => [Rule::requiredIf($this->boolean('has_deposit')), 'nullable', 'regex:/^(\d{1,10})(?:\.(\d{1,2}))?$/'],
            'deposit.payment_method' => [Rule::requiredIf($this->boolean('has_deposit')), 'nullable', Rule::in(['cash', 'card'])],
            'deposit.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_name.required' => 'Escribe el nombre de la clienta.',
            'client_name.max' => 'El nombre de la clienta no puede superar 120 caracteres.',
            'client_phone.max' => 'El teléfono no puede superar 30 caracteres.',
            'date.required' => 'Selecciona la fecha de la cita.',
            'date.date_format' => 'La fecha de la cita no es válida.',
            'start_time.required' => 'Selecciona la hora de inicio.',
            'start_time.date_format' => 'La hora de inicio no es válida.',
            'items.required' => 'Agrega al menos un servicio.',
            'items.*.assigned_to.required' => 'Selecciona la persona que realizará el servicio.',
            'items.*.duration_minutes.required' => 'Indica la duración reservada.',
            'items.*.duration_minutes.min' => 'La duración mínima es 5 minutos.',
            'items.*.duration_minutes.max' => 'La duración máxima es 480 minutos.',
            'items.*.duration_minutes.multiple_of' => 'La duración debe usar intervalos de 5 minutos.',
            'notes.max' => 'Las notas no pueden superar 1000 caracteres.',
            'deposit.amount.required' => 'Escribe el monto del adelanto.',
            'deposit.amount.regex' => 'El monto del adelanto debe ser válido y usar máximo dos decimales.',
            'deposit.payment_method.required' => 'Selecciona el método de pago del adelanto.',
            'deposit.payment_method.in' => 'El método de pago del adelanto no es válido.',
            'deposit.note.max' => 'La nota del adelanto no puede superar 500 caracteres.',
        ];
    }
}

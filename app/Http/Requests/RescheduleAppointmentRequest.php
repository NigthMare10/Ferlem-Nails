<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class RescheduleAppointmentRequest extends FormRequest
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
            'reschedule_note' => is_string($this->reschedule_note) && trim($this->reschedule_note) !== ''
                ? trim($this->reschedule_note)
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'assignments' => ['nullable', 'array', 'max:100'],
            'assignments.*.appointment_item_id' => ['required', 'integer', 'exists:appointment_items,id'],
            'assignments.*.assigned_to' => ['required', 'integer', 'exists:users,id'],
            'reschedule_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Selecciona la nueva fecha.',
            'date.date_format' => 'La fecha seleccionada no es válida.',
            'start_time.required' => 'Selecciona la nueva hora.',
            'start_time.date_format' => 'La hora seleccionada no es válida.',
            'reschedule_note.max' => 'La nota de reprogramación no puede superar 500 caracteres.',
        ];
    }
}

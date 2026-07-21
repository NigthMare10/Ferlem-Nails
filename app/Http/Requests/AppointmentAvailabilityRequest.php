<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class AppointmentAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->is_active && $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS);
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'items' => ['required_without:appointment_id', 'array', 'min:1', 'max:100'],
            'items.*.service_id' => ['required', 'integer', 'exists:services,id'],
            'items.*.assigned_to' => ['required', 'integer', 'exists:users,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.duration_minutes' => ['required', 'integer', 'min:5', 'max:480', 'multiple_of:5'],
            'assignments' => ['nullable', 'array', 'max:100'],
            'assignments.*.appointment_item_id' => ['required', 'integer', 'exists:appointment_items,id'],
            'assignments.*.assigned_to' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Selecciona una fecha.',
            'date.date_format' => 'La fecha seleccionada no es válida.',
            'items.required_without' => 'Selecciona al menos un servicio.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AppointmentsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->is_active
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS)
            && ($user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_OWN)
                || $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL));
    }

    public function rules(): array
    {
        return [
            'view' => ['nullable', 'in:month,day'],
            'month' => ['nullable', 'date_format:Y-m'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.date_format' => 'La fecha seleccionada no es válida.',
            'month.date_format' => 'El mes seleccionado no es válido.',
            'view.in' => 'La vista seleccionada no es válida.',
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $employeeId = $this->input('employee_id');
            if (! $employeeId) return;
            $user = $this->user();
            if (! $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL)) {
                $validator->errors()->add('employee_id', 'No puedes filtrar la agenda de otra persona.');
                return;
            }
            if (! User::query()->whereKey($employeeId)->where('is_active', true)->permission(Permissions::APPOINTMENTS_PERFORM)->exists()) {
                $validator->errors()->add('employee_id', 'La persona seleccionada no está disponible para realizar citas.');
            }
        }];
    }
}

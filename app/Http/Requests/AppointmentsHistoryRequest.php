<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentsHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->is_active
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS)
            && ($user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL)
                || $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_OWN));
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['client', 'service'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $normalized[$field] = $value === '' ? null : $value;
            }
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in([
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_COMPLETED,
                Appointment::STATUS_CANCELED,
                Appointment::STATUS_NO_SHOW,
            ])],
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'client' => ['nullable', 'string', 'max:120'],
            'service' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.date_format' => 'La fecha inicial no es válida.',
            'date_to.date_format' => 'La fecha final no es válida.',
            'status.in' => 'El estado seleccionado no es válido.',
            'employee_id.integer' => 'La persona seleccionada no es válida.',
            'employee_id.exists' => 'La persona seleccionada no existe.',
            'client.string' => 'La búsqueda de clienta no es válida.',
            'client.max' => 'La búsqueda de clienta no puede superar 120 caracteres.',
            'service.string' => 'La búsqueda de servicio no es válida.',
            'service.max' => 'La búsqueda de servicio no puede superar 120 caracteres.',
            'page.integer' => 'La página solicitada no es válida.',
            'page.min' => 'La página solicitada no es válida.',
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (! $validator->errors()->hasAny(['date_from', 'date_to'])) {
                $from = $this->parsedDate('date_from');
                $to = $this->parsedDate('date_to');
                if ($from && $to && $to->lessThan($from)) {
                    $validator->errors()->add('date_to', 'La fecha final debe ser igual o posterior a la fecha inicial.');
                } elseif ($from && $to && $from->diffInDays($to) > 365) {
                    $validator->errors()->add('date_to', 'El rango no puede superar 366 días inclusivos.');
                }
            }

            $employeeId = $this->integer('employee_id');
            if ($employeeId
                && ! $validator->errors()->has('employee_id')
                && ! $this->user()->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL)
                && $employeeId !== $this->user()->getKey()) {
                $validator->errors()->add('employee_id', 'No puedes filtrar el historial de otra persona.');
            }
        }];
    }

    private function parsedDate(string $field): ?CarbonImmutable
    {
        $value = $this->input($field);

        return is_string($value) && $value !== ''
            ? CarbonImmutable::createFromFormat('!Y-m-d', $value)
            : null;
    }
}

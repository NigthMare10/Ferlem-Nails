<?php

namespace App\Http\Requests;

use App\Models\Sale;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SalesEarningsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->is_active
            && $user->hasPermissionTo(Permissions::REPORTS_SALES_VIEW);
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'period' => $this->input('period', 'today'),
            'mode' => $this->input(
                'mode',
                $this->user()?->can(Permissions::APPOINTMENTS_VIEW_PROJECTION) ? 'both' : 'actual',
            ),
        ];

        foreach (['date', 'date_from', 'date_to', 'employee_id', 'payment_method'] as $field) {
            $value = $this->input($field);
            $normalized[$field] = is_string($value) && trim($value) === '' ? null : $value;
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'period' => ['required', Rule::in(['today', 'week', 'month', 'custom'])],
            'mode' => [
                'required',
                Rule::in($this->user()?->can(Permissions::APPOINTMENTS_VIEW_PROJECTION)
                    ? ['actual', 'projection', 'both']
                    : ['actual']),
            ],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'payment_method' => ['nullable', Rule::in([Sale::PAYMENT_METHOD_CASH, Sale::PAYMENT_METHOD_CARD])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('period') !== 'custom'
                || $validator->errors()->hasAny(['date_from', 'date_to'])) {
                return;
            }

            $from = CarbonImmutable::createFromFormat('Y-m-d', $this->string('date_from')->toString(), 'America/Tegucigalpa');
            $to = CarbonImmutable::createFromFormat('Y-m-d', $this->string('date_to')->toString(), 'America/Tegucigalpa');

            if ($from->diffInDays($to) > 365) {
                $validator->errors()->add('date_to', 'El rango personalizado no puede superar 366 días.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'period.required' => 'Selecciona un periodo.',
            'period.in' => 'El periodo seleccionado no es válido.',
            'mode.in' => 'No tienes permiso para consultar la proyección seleccionada.',
            'date.date_format' => 'La fecha de referencia debe usar el formato año-mes-día.',
            'date_from.required_if' => 'Selecciona la fecha inicial.',
            'date_from.date_format' => 'La fecha inicial debe usar el formato año-mes-día.',
            'date_to.required_if' => 'Selecciona la fecha final.',
            'date_to.date_format' => 'La fecha final debe usar el formato año-mes-día.',
            'date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'employee_id.integer' => 'El empleado seleccionado no es válido.',
            'employee_id.exists' => 'El empleado seleccionado no existe.',
            'payment_method.in' => 'El método de pago debe ser efectivo o tarjeta.',
        ];
    }
}

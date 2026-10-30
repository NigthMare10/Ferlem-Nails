<?php

namespace App\Http\Requests;

use App\Models\Expense;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpensesIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active
            && $this->user()->can(Permissions::EXPENSES_ACCESS)
            && $this->user()->can(Permissions::EXPENSES_VIEW);
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['search', 'date_from', 'date_to', 'category_id', 'status', 'payment_method', 'employee_id', 'recorded_by'] as $field) {
            $value = $this->input($field);
            $normalized[$field] = is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value);
        }
        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'status' => ['nullable', Rule::in([Expense::STATUS_RECORDED, Expense::STATUS_CANCELED])],
            'payment_method' => ['nullable', Rule::in([Expense::PAYMENT_METHOD_CASH, Expense::PAYMENT_METHOD_CARD, Expense::PAYMENT_METHOD_TRANSFER])],
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'recorded_by' => ['nullable', 'integer', 'exists:users,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($validator->errors()->hasAny(['date_from', 'date_to'])) {
                return;
            }
            $from = $this->parsedDate('date_from');
            $to = $this->parsedDate('date_to');
            if ($from && $to && $to->lessThan($from)) {
                $validator->errors()->add('date_to', 'La fecha final debe ser igual o posterior a la fecha inicial.');
            } elseif ($from && $to && $from->diffInDays($to) > 365) {
                $validator->errors()->add('date_to', 'El rango no puede superar 366 días inclusivos.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'La búsqueda no puede superar 120 caracteres.',
            'date_from.date_format' => 'La fecha inicial no es válida.',
            'date_to.date_format' => 'La fecha final no es válida.',
            'category_id.exists' => 'La categoría seleccionada no existe.',
            'status.in' => 'El estado seleccionado no es válido.',
            'payment_method.in' => 'El método seleccionado no es válido.',
            'employee_id.exists' => 'El empleado seleccionado no existe.',
            'recorded_by.exists' => 'El usuario seleccionado no existe.',
        ];
    }

    private function parsedDate(string $field): ?CarbonImmutable
    {
        $value = $this->input($field);

        return is_string($value) && $value !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : null;
    }
}

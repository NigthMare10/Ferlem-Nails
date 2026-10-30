<?php

namespace App\Http\Requests;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Support\Permissions;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active
            && $this->user()->can(Permissions::EXPENSES_ACCESS)
            && $this->user()->can(Permissions::EXPENSES_CREATE);
    }

    protected function prepareForValidation(): void
    {
        $token = $this->input('checkout_token');
        $this->merge([
            ...$this->normalizedText(),
            'checkout_token' => is_string($token) ? strtolower(trim($token)) : $token,
            'employee_id' => $this->input('employee_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'checkout_token' => ['required', 'uuid'],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'description' => ['required', 'string', 'min:3', 'max:500'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'payment_method' => ['required', Rule::in([Expense::PAYMENT_METHOD_CASH, Expense::PAYMENT_METHOD_CARD, Expense::PAYMENT_METHOD_TRANSFER])],
            'vendor' => ['nullable', 'string', 'max:255'],
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf'])->max('5mb')],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (! $validator->errors()->has('expense_date') && $this->filled('expense_date')) {
                $date = CarbonImmutable::createFromFormat('!Y-m-d', $this->string('expense_date')->toString(), ReportPeriod::TIMEZONE);
                if ($date->isAfter(CarbonImmutable::now(ReportPeriod::TIMEZONE)->startOfDay())) {
                    $validator->errors()->add('expense_date', 'La fecha del gasto no puede ser futura.');
                }
            }
            if (! $validator->errors()->has('category_id') && $this->filled('category_id')
                && ! ExpenseCategory::query()->whereKey($this->integer('category_id'))->where('is_active', true)->exists()) {
                $validator->errors()->add('category_id', 'La categoría seleccionada está inactiva.');
            }
            if (! $validator->errors()->has('employee_id') && $this->filled('employee_id')
                && ! User::query()->whereKey($this->integer('employee_id'))->where('is_active', true)->exists()) {
                $validator->errors()->add('employee_id', 'El empleado relacionado debe estar activo.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'checkout_token.required' => 'No se pudo identificar esta confirmación. Recarga el formulario.',
            'expense_date.required' => 'Selecciona la fecha del gasto.',
            'category_id.required' => 'Selecciona una categoría.',
            'description.required' => 'Escribe una descripción.',
            'description.min' => 'La descripción debe tener al menos 3 caracteres.',
            'amount.required' => 'Ingresa el monto.',
            'amount.decimal' => 'El monto puede tener como máximo dos decimales.',
            'amount.gt' => 'El monto debe ser mayor que cero.',
            'payment_method.required' => 'Selecciona el método de pago.',
            'attachment' => 'El comprobante debe ser JPG, PNG, WEBP o PDF y no superar 5 MB.',
        ];
    }

    private function normalizedText(): array
    {
        $values = [];
        foreach (['description', 'vendor', 'notes'] as $field) {
            $value = $this->input($field);
            $values[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        return $values;
    }
}

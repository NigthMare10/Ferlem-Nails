<?php

namespace App\Http\Requests;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Support\Permissions;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;

class UpdateExpenseRequest extends StoreExpenseRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active
            && $this->user()->can(Permissions::EXPENSES_ACCESS)
            && $this->user()->can(Permissions::EXPENSES_UPDATE);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['checkout_token'], $rules['notes'], $rules['attachment']);

        return $rules;
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
            $expense = $this->route('expense');
            if (! $expense instanceof Expense) {
                return;
            }
            if (! $validator->errors()->has('category_id') && $this->filled('category_id')
                && (int) $this->input('category_id') !== (int) $expense->category_id
                && ! ExpenseCategory::query()->whereKey($this->integer('category_id'))->where('is_active', true)->exists()) {
                $validator->errors()->add('category_id', 'La categoría seleccionada está inactiva.');
            }
            if (! $validator->errors()->has('employee_id') && $this->filled('employee_id')
                && (int) $this->input('employee_id') !== (int) $expense->employee_id
                && ! User::query()->whereKey($this->integer('employee_id'))->where('is_active', true)->exists()) {
                $validator->errors()->add('employee_id', 'El empleado relacionado debe estar activo.');
            }
        }];
    }
}

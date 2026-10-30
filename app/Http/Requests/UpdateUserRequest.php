<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.update');
    }

    public function rules(): array
    {
        $employmentRequired = $this->input('role') === 'employee' || $this->boolean('has_employment_profile');

        return ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role' => ['nullable', Rule::in(['owner', 'administrator', 'employee'])],
            'has_employment_profile' => ['sometimes', 'boolean'],
            'monthly_salary' => [$employmentRequired ? 'required' : 'nullable', 'regex:/^\d{1,10}(\.\d{1,2})?$/', 'gt:0'],
            'contract_start_date' => [$employmentRequired ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'is_indefinite' => [$employmentRequired ? 'required' : 'nullable', 'boolean'],
            'contract_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:contract_start_date', Rule::requiredIf($employmentRequired && ! $this->boolean('is_indefinite'))],
            'default_payment_method' => [Rule::requiredIf($employmentRequired && $this->boolean('auto_generate_payroll_expense')), 'nullable', Rule::in(['cash', 'card', 'transfer'])],
            'auto_generate_payroll_expense' => [$employmentRequired ? 'required' : 'nullable', 'boolean'],
        ];
    }
}

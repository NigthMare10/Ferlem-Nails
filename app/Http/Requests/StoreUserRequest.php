<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.create');
    }

    public function rules(): array
    {
        $employmentRequired = $this->input('role') === 'employee' || $this->boolean('has_employment_profile');

        return ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)], 'role' => ['required', Rule::in(['owner', 'administrator', 'employee'])],
            'is_active' => ['required', 'boolean'],
            'has_employment_profile' => ['sometimes', 'boolean'],
            ...$this->employmentRules($employmentRequired),
        ];
    }

    private function employmentRules(bool $required): array
    {
        $presence = $required ? 'required' : 'nullable';

        return [
            'monthly_salary' => [$presence, 'regex:/^\d{1,10}(\.\d{1,2})?$/', 'gt:0'],
            'contract_start_date' => [$presence, 'date_format:Y-m-d'],
            'is_indefinite' => [$required ? 'required' : 'nullable', 'boolean'],
            'contract_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:contract_start_date', Rule::requiredIf($required && ! $this->boolean('is_indefinite'))],
            'default_payment_method' => [Rule::requiredIf($required && $this->boolean('auto_generate_payroll_expense')), 'nullable', Rule::in(['cash', 'card', 'transfer'])],
            'auto_generate_payroll_expense' => [$required ? 'required' : 'nullable', 'boolean'],
        ];
    }
}

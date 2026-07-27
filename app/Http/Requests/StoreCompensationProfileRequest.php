<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompensationProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.configure') ?? false;
    }

    public function rules(): array
    {
        return [
            'monthly_salary' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/', 'gt:0'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'contract_start_date' => ['required', 'date_format:Y-m-d'],
            'contract_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:contract_start_date', 'required_if:is_indefinite,false'],
            'is_indefinite' => ['required', 'boolean'],
            'default_payment_method' => ['nullable', 'required_if:auto_generate_payroll_expense,true', 'in:cash,card,transfer'],
            'auto_generate_payroll_expense' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

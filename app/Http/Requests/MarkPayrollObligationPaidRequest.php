<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkPayrollObligationPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.mark_paid') && $this->user()?->can('expenses.create');
    }

    public function rules(): array
    {
        return ['expense_date' => ['required', 'date', 'before_or_equal:today'], 'payment_method' => ['required', 'in:cash,card,transfer'], 'notes' => ['nullable', 'string', 'max:1000'], 'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120']];
    }
}

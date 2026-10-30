<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class CancelExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active
            && $this->user()->can(Permissions::EXPENSES_ACCESS)
            && $this->user()->can(Permissions::EXPENSES_CANCEL);
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('cancellation_reason');
        $this->merge(['cancellation_reason' => is_string($reason) ? trim($reason) : $reason]);
    }

    public function rules(): array
    {
        return ['cancellation_reason' => ['required', 'string', 'min:5', 'max:500']];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Explica el motivo de la anulación.',
            'cancellation_reason.min' => 'El motivo debe tener al menos 5 caracteres.',
            'cancellation_reason.max' => 'El motivo no puede superar 500 caracteres.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\ExpenseCategory;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active
            && $this->user()->can(Permissions::EXPENSES_ACCESS)
            && $this->user()->can(Permissions::EXPENSES_MANAGE_CATEGORIES);
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $this->merge(['name' => is_string($name) ? trim($name) : $name]);
    }

    public function rules(): array
    {
        $category = $this->route('expenseCategory');

        return [
            'name' => ['required', 'string', 'min:2', 'max:100', Rule::unique('expense_categories', 'name')->ignore($category instanceof ExpenseCategory ? $category->getKey() : null)],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseCategoryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active
            && $this->user()->can(Permissions::EXPENSES_ACCESS)
            && $this->user()->can(Permissions::EXPENSES_MANAGE_CATEGORIES);
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}

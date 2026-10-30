<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class SendDailyCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::DAILY_CLOSE_SEND) === true;
    }

    public function rules(): array
    {
        return ['date' => ['nullable', 'date_format:Y-m-d']];
    }
}

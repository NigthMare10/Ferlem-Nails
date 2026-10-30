<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Permissions::SETTINGS_BUSINESS_HOURS_MANAGE);
    }

    public function rules(): array
    {
        return [
            'hours' => ['required', 'array', 'size:7'],
            'hours.*.weekday' => ['required', 'integer', 'between:1,7', 'distinct'],
            'hours.*.is_open' => ['required', 'boolean'],
            'hours.*.opens_at' => ['nullable', 'date_format:H:i', 'required_if:hours.*.is_open,true'],
            'hours.*.closes_at' => ['nullable', 'date_format:H:i', 'required_if:hours.*.is_open,true', function (string $attribute, mixed $value, Closure $fail): void {
                $index = (int) explode('.', $attribute)[1];
                $opensAt = $this->input("hours.$index.opens_at");
                if ($this->boolean("hours.$index.is_open") && is_string($opensAt) && is_string($value) && $opensAt >= $value) {
                    $fail('La hora de apertura debe ser anterior a la hora de cierre.');
                }
            }],
        ];
    }
}

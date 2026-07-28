<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDailyCloseSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::DAILY_CLOSE_MANAGE) === true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'send_time' => ['required', 'date_format:H:i'],
            'recipient_emails' => [Rule::requiredIf($this->boolean('enabled')), 'array', 'max:20'],
            'recipient_emails.*' => ['required', 'email:rfc', 'max:254', 'distinct:ignore_case'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $recipients = collect($this->input('recipient_emails', []))
            ->filter(fn ($email) => is_string($email) && trim($email) !== '')
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->values()
            ->all();

        $this->merge([
            'recipient_emails' => $recipients,
        ]);
    }

    public function messages(): array
    {
        return [
            'recipient_emails.required' => 'Agrega al menos un destinatario para activar el envío diario.',
            'recipient_emails.*.email' => 'Todos los destinatarios deben ser correos válidos.',
            'recipient_emails.*.distinct' => 'No puedes repetir un destinatario.',
        ];
    }
}

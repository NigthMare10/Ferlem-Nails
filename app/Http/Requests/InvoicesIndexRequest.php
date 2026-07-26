<?php

namespace App\Http\Requests;

use App\Models\Sale;
use App\Support\Permissions;
use App\Support\SaleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoicesIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && SaleAccess::canList($this->user());
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['search', 'status', 'method', 'proof_status'] as $field) {
            $value = $this->input($field);
            $normalized[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }
        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in([Sale::STATUS_COMPLETED, Sale::STATUS_CANCELED])],
            'method' => ['nullable', Rule::in([
                Sale::PAYMENT_METHOD_CASH,
                Sale::PAYMENT_METHOD_CARD,
                Sale::PAYMENT_METHOD_TRANSFER,
                'mixed',
            ])],
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'proof_status' => ['nullable', Rule::in(['with_proof', 'pending'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (! $validator->errors()->hasAny(['date_from', 'date_to'])) {
                $from = $this->parsedDate('date_from');
                $to = $this->parsedDate('date_to');
                if ($from && $to && $to->lessThan($from)) {
                    $validator->errors()->add('date_to', 'La fecha final debe ser igual o posterior a la fecha inicial.');
                } elseif ($from && $to && $from->diffInDays($to) > 365) {
                    $validator->errors()->add('date_to', 'El rango no puede superar 366 días inclusivos.');
                }
            }

            if ($this->filled('employee_id')
                && ! $this->user()->can(Permissions::SALES_VIEW_ALL)) {
                $validator->errors()->add('employee_id', 'No puedes filtrar facturas de otra persona.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'La búsqueda no puede superar 120 caracteres.',
            'date_from.date_format' => 'La fecha inicial no es válida.',
            'date_to.date_format' => 'La fecha final no es válida.',
            'status.in' => 'El estado seleccionado no es válido.',
            'method.in' => 'El método seleccionado no es válido.',
            'employee_id.exists' => 'La persona seleccionada no existe.',
            'proof_status.in' => 'El estado de captura no es válido.',
        ];
    }

    private function parsedDate(string $field): ?CarbonImmutable
    {
        $value = $this->input($field);

        return is_string($value) && $value !== ''
            ? CarbonImmutable::createFromFormat('!Y-m-d', $value)
            : null;
    }
}

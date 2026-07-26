<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreInvoiceTransferProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active
            && $this->user()->can(Permissions::SALES_UPLOAD_TRANSFER_PROOF);
    }

    public function rules(): array
    {
        return [
            'payment_proof' => [
                'required',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_proof.required' => 'Selecciona una captura.',
            'payment_proof.mimes' => 'La captura debe ser JPG, PNG o WEBP.',
            'payment_proof.max' => 'La captura no puede superar 5 MB.',
        ];
    }
}

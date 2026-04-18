<?php

namespace App\Modules\VentasPOS\Http\Requests;

class CheckoutPosRequest extends StoreOrderRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'payment_method' => ['nullable', 'in:efectivo,tarjeta_manual,transferencia'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);
    }
}

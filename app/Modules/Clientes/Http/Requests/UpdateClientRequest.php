<?php

namespace App\Modules\Clientes\Http\Requests;

class UpdateClientRequest extends StoreClientRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clientes.editar') ?? false;
    }
}

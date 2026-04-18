<?php

namespace App\Modules\Clientes\Policies;

use App\Models\User;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Shared\Policies\Concerns\AuthorizesBranchScope;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Auth\Access\Response;

class ClientePolicy
{
    use AuthorizesBranchScope;

    public function viewAny(User $user, Sucursal $sucursal): Response
    {
        return $this->authorizeForBranch($user, $sucursal, 'clientes.ver');
    }

    public function create(User $user, Sucursal $sucursal): Response
    {
        return $this->authorizeForBranch($user, $sucursal, 'clientes.crear');
    }

    public function view(User $user, Cliente $cliente, Sucursal $sucursal): Response
    {
        if (! $cliente->perfiles()->where('sucursal_id', $sucursal->id)->exists()) {
            return Response::denyAsNotFound();
        }

        return $this->authorizeForBranch($user, $sucursal, 'clientes.ver');
    }

    public function update(User $user, Cliente $cliente, Sucursal $sucursal): Response
    {
        if (! $cliente->perfiles()->where('sucursal_id', $sucursal->id)->exists()) {
            return Response::denyAsNotFound();
        }

        return $this->authorizeForBranch($user, $sucursal, 'clientes.editar');
    }
}

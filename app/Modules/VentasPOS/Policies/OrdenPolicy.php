<?php

namespace App\Modules\VentasPOS\Policies;

use App\Models\User;
use App\Modules\Shared\Policies\Concerns\AuthorizesBranchScope;
use App\Modules\Sucursales\Models\Sucursal;
use App\Modules\VentasPOS\Models\Orden;
use Illuminate\Auth\Access\Response;

class OrdenPolicy
{
    use AuthorizesBranchScope;

    public function create(User $user, Sucursal $sucursal): Response
    {
        return $this->authorizeForBranch($user, $sucursal, 'pos.usar');
    }

    public function view(User $user, Orden $orden, Sucursal $sucursal): Response
    {
        return $this->authorizeModelInBranch($user, $sucursal, $orden->sucursal_id, 'pos.usar');
    }
}

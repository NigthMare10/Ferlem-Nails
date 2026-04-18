<?php

namespace App\Modules\Sucursales\Policies;

use App\Models\User;
use App\Modules\Shared\Policies\Concerns\AuthorizesBranchScope;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Auth\Access\Response;

class SucursalPolicy
{
    use AuthorizesBranchScope;

    public function activate(User $user, Sucursal $sucursal): Response
    {
        return $this->authorizeForBranch($user, $sucursal, 'sucursales.seleccionar');
    }
}

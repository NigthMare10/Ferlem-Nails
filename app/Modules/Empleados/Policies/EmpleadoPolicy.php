<?php

namespace App\Modules\Empleados\Policies;

use App\Models\User;
use App\Modules\Empleados\Models\Empleado;
use App\Modules\Shared\Policies\Concerns\AuthorizesBranchScope;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Auth\Access\Response;

class EmpleadoPolicy
{
    use AuthorizesBranchScope;

    public function viewAny(User $user, Sucursal $sucursal): Response
    {
        return $this->authorizeForBranch($user, $sucursal, 'empleados.ver');
    }

    public function view(User $user, Empleado $empleado, Sucursal $sucursal): Response
    {
        if (! $empleado->sucursales()->where('sucursal_id', $sucursal->id)->exists()) {
            return Response::denyAsNotFound();
        }

        return $this->authorizeForBranch($user, $sucursal, 'empleados.ver');
    }
}

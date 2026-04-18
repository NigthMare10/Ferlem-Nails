<?php

namespace App\Modules\Agenda\Policies;

use App\Models\User;
use App\Modules\Agenda\Models\Cita;
use App\Modules\Shared\Policies\Concerns\AuthorizesBranchScope;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Auth\Access\Response;

class CitaPolicy
{
    use AuthorizesBranchScope;

    public function viewAny(User $user, Sucursal $sucursal): Response
    {
        return $this->authorizeForBranch($user, $sucursal, 'agenda.ver');
    }

    public function create(User $user, Sucursal $sucursal): Response
    {
        return $this->authorizeForBranch($user, $sucursal, 'agenda.crear');
    }

    public function view(User $user, Cita $cita, Sucursal $sucursal): Response
    {
        return $this->authorizeModelInBranch($user, $sucursal, $cita->sucursal_id, 'agenda.ver');
    }
}

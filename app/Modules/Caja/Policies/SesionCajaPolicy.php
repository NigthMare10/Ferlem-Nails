<?php

namespace App\Modules\Caja\Policies;

use App\Models\User;
use App\Modules\Caja\Models\SesionCaja;
use App\Modules\Shared\Policies\Concerns\AuthorizesBranchScope;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Auth\Access\Response;

class SesionCajaPolicy
{
    use AuthorizesBranchScope;

    public function viewAny(User $user, Sucursal $sucursal): Response
    {
        return $this->authorizeForBranch($user, $sucursal, 'caja.ver');
    }

    public function open(User $user, Sucursal $sucursal): Response
    {
        return $this->authorizeForBranch($user, $sucursal, 'caja.abrir');
    }

    public function view(User $user, SesionCaja $sesionCaja, Sucursal $sucursal): Response
    {
        $response = $this->authorizeModelInBranch($user, $sucursal, $sesionCaja->sucursal_id, 'caja.ver');

        if ($response->denied()) {
            return $response;
        }

        if ($sesionCaja->user_id !== $user->id && ! $user->can('caja.cerrar_ajena')) {
            return Response::denyAsNotFound();
        }

        return Response::allow();
    }

    public function close(User $user, SesionCaja $sesionCaja, Sucursal $sucursal): Response
    {
        $response = $this->authorizeModelInBranch($user, $sucursal, $sesionCaja->sucursal_id, 'caja.cerrar');

        if ($response->denied()) {
            return $response;
        }

        if ($sesionCaja->user_id !== $user->id && ! $user->can('caja.cerrar_ajena')) {
            return Response::deny('No puedes cerrar una caja abierta por otro usuario.');
        }

        return Response::allow();
    }
}

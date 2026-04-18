<?php

namespace App\Modules\Facturacion\Policies;

use App\Models\User;
use App\Modules\Facturacion\Models\Factura;
use App\Modules\Shared\Policies\Concerns\AuthorizesBranchScope;
use App\Modules\Sucursales\Models\Sucursal;
use App\Modules\VentasPOS\Models\Orden;
use Illuminate\Auth\Access\Response;

class FacturaPolicy
{
    use AuthorizesBranchScope;

    public function viewAny(User $user, Sucursal $sucursal): Response
    {
        return $this->authorizeForBranch($user, $sucursal, 'facturas.ver');
    }

    public function view(User $user, Factura $factura, Sucursal $sucursal): Response
    {
        return $this->authorizeModelInBranch($user, $sucursal, $factura->sucursal_id, 'facturas.ver');
    }

    public function emit(User $user, Orden $orden, Sucursal $sucursal): Response
    {
        return $this->authorizeModelInBranch($user, $sucursal, $orden->sucursal_id, 'facturas.emitir');
    }
}

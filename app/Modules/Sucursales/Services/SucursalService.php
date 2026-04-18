<?php

namespace App\Modules\Sucursales\Services;

use App\Models\User;
use App\Modules\Auditoria\Services\AuditService;
use App\Modules\IdentidadAcceso\Services\AdminReauthenticationService;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SucursalService
{
    public function __construct(
        protected AuditService $auditService,
        protected AdminReauthenticationService $adminReauthenticationService,
    ) {
    }

    public function disponiblesParaUsuario(User $user): Collection
    {
        return $user->sucursales()->with('configuracion')->orderBy('name')->get();
    }

    public function activarSucursal(User $user, Sucursal $sucursal, Request $request): void
    {
        abort_unless($user->sucursales()->whereKey($sucursal->id)->exists(), 403, 'No puedes operar sobre esta sucursal.');

        $this->adminReauthenticationService->invalidate($request);
        $request->session()->put('active_branch_id', $sucursal->id);

        $this->auditService->log(
            action: 'sucursal.activada',
            actor: $user,
            branchId: $sucursal->id,
            description: 'El usuario cambió su contexto operativo de sucursal.',
            auditable: $sucursal,
            request: $request,
        );
    }
}

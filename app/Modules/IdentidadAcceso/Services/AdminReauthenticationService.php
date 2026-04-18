<?php

namespace App\Modules\IdentidadAcceso\Services;

use App\Models\User;
use App\Modules\Auditoria\Services\AuditService;
use App\Modules\IdentidadAcceso\Support\SecurityRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminReauthenticationService
{
    public function __construct(protected AuditService $auditService)
    {
    }

    public function confirm(User $user, string $password, Request $request): void
    {
        if (! $request->hasSession()) {
            throw ValidationException::withMessages([
                'password' => 'La reautenticación administrativa requiere una sesión segura activa.',
            ]);
        }

        if (! $user->hasAnyRole(SecurityRoles::administrativeRoles())) {
            throw ValidationException::withMessages([
                'password' => 'El usuario autenticado no puede ejecutar confirmaciones administrativas.',
            ]);
        }

        if (! Hash::check($password, $user->password)) {
            $this->auditService->log(
                action: 'seguridad.reautenticacion_admin_fallida',
                actor: $user,
                branchId: (int) $request->session()->get('active_branch_id'),
                description: 'La reautenticación administrativa falló por contraseña inválida.',
                request: $request,
            );

            throw ValidationException::withMessages([
                'password' => 'La contraseña ingresada no es válida.',
            ]);
        }

        $request->session()->put([
            'admin_reauthenticated_at' => now(),
            'admin_reauthenticated_user_id' => $user->id,
            'admin_reauthenticated_branch_id' => (int) $request->session()->get('active_branch_id'),
        ]);

        $branch = $user->sucursales()->with('configuracion')->whereKey((int) $request->session()->get('active_branch_id'))->first();
        $ttlMinutes = (int) ($branch?->configuracion?->ventana_reautenticacion_minutos ?? 15);

        $this->auditService->log(
            action: 'seguridad.reautenticacion_admin_exitosa',
            actor: $user,
            branchId: (int) $request->session()->get('active_branch_id'),
            description: 'El usuario confirmó su contraseña para ejecutar una acción sensible.',
            metadata: [
                'ttl_minutos' => $ttlMinutes,
            ],
            request: $request,
        );
    }

    public function invalidate(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $request->session()->forget([
            'admin_reauthenticated_at',
            'admin_reauthenticated_user_id',
            'admin_reauthenticated_branch_id',
        ]);
    }
}

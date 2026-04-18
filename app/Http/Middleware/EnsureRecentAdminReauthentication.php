<?php

namespace App\Http\Middleware;

use App\Modules\IdentidadAcceso\Support\SecurityRoles;
use App\Modules\Sucursales\Support\SucursalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecentAdminReauthentication
{
    public function __construct(protected SucursalContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->hasAnyRole(SecurityRoles::administrativeRoles())) {
            abort(403, 'La acción requiere privilegios administrativos.');
        }

        if (! $request->hasSession()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'La reautenticación administrativa solo está disponible en el flujo first-party con sesión segura.',
                ], 428);
            }

            abort(428, 'La sesión segura requerida para reautenticación no está disponible.');
        }

        $confirmedAt = $request->session()->get('admin_reauthenticated_at');
        $confirmedUserId = (int) $request->session()->get('admin_reauthenticated_user_id');
        $confirmedBranchId = (int) $request->session()->get('admin_reauthenticated_branch_id');

        $minutes = (int) optional($this->context->get()?->configuracion)->ventana_reautenticacion_minutos ?: 15;
        $branchId = (int) $this->context->id();

        if (
            ! $confirmedAt
            || now()->diffInMinutes($confirmedAt) >= $minutes
            || $confirmedUserId !== (int) $user->id
            || $confirmedBranchId !== $branchId
        ) {
            $request->session()->forget([
                'admin_reauthenticated_at',
                'admin_reauthenticated_user_id',
                'admin_reauthenticated_branch_id',
            ]);

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Debes confirmar tu contraseña para ejecutar esta acción sensible en la sucursal activa.',
                ], 423);
            }

            return redirect()->route('auth.reautenticacion');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Modules\Sucursales\Models\Sucursal;
use App\Modules\Sucursales\Support\SucursalContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveBranch
{
    public function __construct(protected SucursalContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $headerBranchPublicId = $request->header('X-Branch-Public-Id');
        $isApiRequest = $request->expectsJson() || $request->is('api/*');

        if (! $user) {
            return redirect()->route('login');
        }

        $activeBranchId = $request->hasSession()
            ? (int) $request->session()->get('active_branch_id')
            : 0;

        if ($activeBranchId && $isApiRequest && $headerBranchPublicId) {
            $sessionBranch = $user->sucursales()->whereKey($activeBranchId)->first();

            if (! $sessionBranch instanceof Sucursal) {
                if ($request->hasSession()) {
                    $request->session()->forget('active_branch_id');
                }

                return $this->redirectToSelector($request);
            }

            if ($sessionBranch->public_id !== $headerBranchPublicId) {
                return response()->json([
                    'message' => 'La cabecera X-Branch-Public-Id no coincide con la sucursal activa de la sesión.',
                ], 409);
            }
        }

        if (! $activeBranchId && $isApiRequest && $headerBranchPublicId) {
            $branch = $user->sucursales()->where('public_id', $headerBranchPublicId)->first();

            if ($branch instanceof Sucursal) {
                $this->context->set($branch);

                return $next($request);
            }
        }

        if (! $activeBranchId) {
            return $this->redirectToSelector($request);
        }

        $branch = $user->sucursales()->whereKey($activeBranchId)->first();

        if (! $branch instanceof Sucursal) {
            if ($request->hasSession()) {
                $request->session()->forget('active_branch_id');
            }

            return $this->redirectToSelector($request);
        }

        $this->context->set($branch);

        return $next($request);
    }

    protected function redirectToSelector(Request $request): RedirectResponse|Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Debes seleccionar una sucursal activa o enviar X-Branch-Public-Id antes de continuar.',
            ], 409);
        }

        return redirect()->route('sucursales.selector');
    }
}

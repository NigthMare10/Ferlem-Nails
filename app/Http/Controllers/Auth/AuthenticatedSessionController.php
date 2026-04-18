<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Modules\Auditoria\Services\AuditService;
use App\Modules\IdentidadAcceso\Services\AccessProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        protected AccessProfileService $accessProfileService,
        protected AuditService $auditService,
    ) {
    }

    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'profiles' => $this->accessProfileService->visibleProfiles(),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $request->authenticate();

        $request->session()->regenerate();
        $request->session()->forget([
            'admin_reauthenticated_at',
            'admin_reauthenticated_user_id',
            'admin_reauthenticated_branch_id',
        ]);

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();

        $defaultBranch = $this->accessProfileService->resolveActiveBranchAfterLogin($user);

        if ($defaultBranch) {
            $request->session()->put('active_branch_id', $defaultBranch->id);
        } else {
            $request->session()->forget('active_branch_id');
        }

        $this->auditService->log(
            action: 'auth.login',
            actor: $user,
            branchId: $defaultBranch?->id,
            description: 'Inicio de sesion exitoso por perfil operativo.',
            metadata: [
                'profile_public_id' => $user->public_id,
                'branch_resolution' => $defaultBranch ? 'directa' : 'selector_posterior',
            ],
            request: $request,
        );

        return redirect()->intended($this->accessProfileService->resolvePostLoginPath($user, $defaultBranch));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            $this->auditService->log(
                action: 'auth.logout',
                actor: $user,
                branchId: (int) $request->session()->get('active_branch_id'),
                description: 'Cierre de sesión del usuario.',
                request: $request,
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login');
    }
}

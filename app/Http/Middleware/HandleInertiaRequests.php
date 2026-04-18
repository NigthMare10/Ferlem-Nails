<?php

namespace App\Http\Middleware;

use App\Modules\Sucursales\Http\Resources\BranchResource;
use App\Modules\Sucursales\Support\SucursalContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(protected SucursalContext $branchContext)
    {
    }

    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'public_id' => $user->public_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->values(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                ] : null,
                'activeBranch' => $this->branchContext->get() ? new BranchResource($this->branchContext->get()) : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'app' => [
                'name' => config('app.name', 'FERLEM NAILS'),
                'currency' => 'HNL',
                'currencySymbol' => 'L',
            ],
        ];
    }
}

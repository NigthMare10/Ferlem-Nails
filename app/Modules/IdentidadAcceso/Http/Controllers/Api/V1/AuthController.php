<?php

namespace App\Modules\IdentidadAcceso\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\IdentidadAcceso\Http\Requests\ReauthenticateAdminRequest;
use App\Modules\IdentidadAcceso\Services\AdminReauthenticationService;
use App\Modules\Sucursales\Http\Resources\BranchResource;
use App\Modules\Sucursales\Support\SucursalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AdminReauthenticationService $reauthenticationService,
        protected SucursalContext $branchContext,
    ) {
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('sucursales.configuracion');

        return response()->json([
            'data' => [
                'public_id' => $user->public_id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->getRoleNames()->values(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                'active_branch' => $this->branchContext->get() ? new BranchResource($this->branchContext->get()) : null,
            ],
        ]);
    }

    public function reauthenticate(ReauthenticateAdminRequest $request): JsonResponse
    {
        $this->reauthenticationService->confirm($request->user(), $request->string('password')->toString(), $request);

        return response()->json([
            'message' => 'La confirmación administrativa se realizó correctamente.',
        ]);
    }
}

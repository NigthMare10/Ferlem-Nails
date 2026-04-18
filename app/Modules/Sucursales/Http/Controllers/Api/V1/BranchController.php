<?php

namespace App\Modules\Sucursales\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Sucursales\Http\Requests\ActivateBranchRequest;
use App\Modules\Sucursales\Http\Resources\BranchResource;
use App\Modules\Sucursales\Models\Sucursal;
use App\Modules\Sucursales\Services\SucursalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function __construct(protected SucursalService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('sucursales.seleccionar'), 403);

        return response()->json([
            'data' => BranchResource::collection($this->service->disponiblesParaUsuario($request->user())),
        ]);
    }

    public function activate(ActivateBranchRequest $request): JsonResponse
    {
        $branch = Sucursal::query()->where('public_id', $request->string('sucursal_public_id'))->firstOrFail();
        $this->authorize('activate', $branch);
        $this->service->activarSucursal($request->user(), $branch, $request);

        return response()->json([
            'message' => 'La sucursal activa fue actualizada correctamente.',
            'data' => new BranchResource($branch->load('configuracion')),
        ]);
    }
}

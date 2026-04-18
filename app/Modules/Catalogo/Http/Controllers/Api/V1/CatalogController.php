<?php

namespace App\Modules\Catalogo\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Catalogo\Http\Resources\ServiceResource;
use App\Modules\Catalogo\Services\CatalogoService;
use App\Modules\Sucursales\Support\SucursalContext;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function __construct(
        protected CatalogoService $catalogoService,
        protected SucursalContext $branchContext,
    ) {
    }

    public function services(): JsonResponse
    {
        $branch = $this->branchContext->required();
        $services = $this->catalogoService->listarActivosParaSucursal($branch)
            ->map(function ($service) use ($branch) {
                $service->resolved_price = $this->catalogoService->precioVigente($service, $branch);

                return $service;
            });

        return response()->json([
            'data' => ServiceResource::collection($services),
        ]);
    }
}

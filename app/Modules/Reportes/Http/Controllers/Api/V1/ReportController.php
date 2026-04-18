<?php

namespace App\Modules\Reportes\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Reportes\Services\ReportService;
use App\Modules\Sucursales\Support\SucursalContext;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $service,
        protected SucursalContext $branchContext,
    ) {
    }

    public function summary(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->resumenSucursal($this->branchContext->required()),
        ]);
    }
}

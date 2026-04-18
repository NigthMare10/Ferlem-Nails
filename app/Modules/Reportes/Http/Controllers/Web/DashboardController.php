<?php

namespace App\Modules\Reportes\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Caja\Services\CajaService;
use App\Modules\Reportes\Services\ReportService;
use App\Modules\Sucursales\Support\SucursalContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected ReportService $reportService,
        protected CajaService $cashService,
        protected SucursalContext $branchContext,
    ) {
    }

    public function __invoke(): Response
    {
        $branch = $this->branchContext->required();
        $canViewReports = request()->user()?->can('reportes.ver_sucursal') || request()->user()?->can('reportes.ver_global');

        return Inertia::render('Dashboard/Index', [
            'resumen' => $canViewReports ? $this->reportService->resumenSucursal($branch) : null,
            'puedeVerReportes' => (bool) $canViewReports,
            'sesionCajaActiva' => $this->cashService->sesionAbierta(request()->user(), $branch),
        ]);
    }
}

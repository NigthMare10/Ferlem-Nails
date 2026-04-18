<?php

namespace App\Modules\Reportes\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Reportes\Services\ReportService;
use App\Modules\Sucursales\Support\SucursalContext;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $service,
        protected SucursalContext $branchContext,
    ) {
    }

    public function __invoke(): Response
    {
        return Inertia::render('Reportes/Index', [
            'resumen' => $this->service->resumenSucursal($this->branchContext->required()),
        ]);
    }
}

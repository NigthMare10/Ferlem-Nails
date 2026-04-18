<?php

namespace App\Modules\Empleados\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Empleados\Models\Empleado;
use App\Modules\Sucursales\Support\SucursalContext;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(protected SucursalContext $branchContext)
    {
    }

    public function __invoke(): Response
    {
        $this->authorize('viewAny', [Empleado::class, $this->branchContext->required()]);

        $employees = Empleado::query()
            ->whereHas('sucursales', fn ($query) => $query->where('sucursal_id', $this->branchContext->id()))
            ->orderBy('name')
            ->get();

        return Inertia::render('Empleados/Index', [
            'empleados' => $employees,
        ]);
    }
}

<?php

namespace App\Modules\Empleados\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Empleados\Models\Empleado;
use App\Modules\Sucursales\Support\SucursalContext;
use Illuminate\Http\JsonResponse;

class EmployeeController extends Controller
{
    public function __construct(protected SucursalContext $branchContext)
    {
    }

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', [Empleado::class, $this->branchContext->required()]);

        $employees = Empleado::query()
            ->whereHas('sucursales', fn ($query) => $query->where('sucursal_id', $this->branchContext->id()))
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'role_title', 'is_active']);

        return response()->json([
            'data' => $employees->map(fn ($employee) => [
                'public_id' => $employee->public_id,
                'name' => $employee->name,
                'role_title' => $employee->role_title,
                'is_active' => $employee->is_active,
            ]),
        ]);
    }
}

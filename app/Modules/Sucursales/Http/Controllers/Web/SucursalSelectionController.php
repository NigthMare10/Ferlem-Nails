<?php

namespace App\Modules\Sucursales\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Sucursales\Http\Requests\ActivateBranchRequest;
use App\Modules\Sucursales\Http\Resources\BranchResource;
use App\Modules\Sucursales\Models\Sucursal;
use App\Modules\Sucursales\Services\SucursalService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SucursalSelectionController extends Controller
{
    public function __construct(protected SucursalService $service)
    {
    }

    public function index(): Response
    {
        abort_unless(request()->user()?->can('sucursales.seleccionar'), 403);

        return Inertia::render('Sucursales/Selector', [
            'sucursales' => BranchResource::collection($this->service->disponiblesParaUsuario(request()->user())),
            'activa' => session('active_branch_id'),
        ]);
    }

    public function update(ActivateBranchRequest $request): RedirectResponse
    {
        $branch = Sucursal::query()->where('public_id', $request->string('sucursal_public_id'))->firstOrFail();
        $this->authorize('activate', $branch);
        $this->service->activarSucursal($request->user(), $branch, $request);

        return redirect()->route('dashboard');
    }
}

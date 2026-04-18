<?php

namespace App\Modules\Caja\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Caja\Http\Requests\CloseCashSessionRequest;
use App\Modules\Caja\Http\Requests\OpenCashSessionRequest;
use App\Modules\Caja\Models\SesionCaja;
use App\Modules\Caja\Services\CajaService;
use App\Modules\Sucursales\Support\SucursalContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CashController extends Controller
{
    public function __construct(
        protected CajaService $service,
        protected SucursalContext $branchContext,
    ) {
    }

    public function index(): Response
    {
        $branch = $this->branchContext->required();
        $this->authorize('viewAny', [SesionCaja::class, $branch]);

        $sessionsQuery = SesionCaja::query()
            ->where('sucursal_id', $branch->id)
            ->latest('opened_at');

        if (! request()->user()->can('caja.cerrar_ajena')) {
            $sessionsQuery->where('user_id', request()->user()->id);
        }

        return Inertia::render('Caja/Index', [
            'sesionActiva' => $this->service->sesionAbierta(request()->user(), $branch),
            'sesionesRecientes' => $sessionsQuery->limit(10)->get(),
        ]);
    }

    public function open(OpenCashSessionRequest $request): RedirectResponse
    {
        $this->authorize('open', [SesionCaja::class, $this->branchContext->required()]);

        $this->service->abrir(
            $request->user(),
            $this->branchContext->required(),
            $request->integer('opening_amount'),
            $request->input('notes'),
            $request,
        );

        return redirect()->route('caja.index')->with('success', 'Caja abierta correctamente.');
    }

    public function close(CloseCashSessionRequest $request, SesionCaja $sesionCaja): RedirectResponse
    {
        $this->authorize('close', [$sesionCaja, $this->branchContext->required()]);

        $this->service->cerrar($sesionCaja, $request->user(), $request->integer('counted_amount'), $request->input('notes'), $request);

        return redirect()->route('caja.index')->with('success', 'Caja cerrada correctamente.');
    }
}

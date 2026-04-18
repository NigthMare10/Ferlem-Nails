<?php

namespace App\Modules\Caja\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Caja\Http\Requests\CloseCashSessionRequest;
use App\Modules\Caja\Http\Requests\OpenCashSessionRequest;
use App\Modules\Caja\Http\Resources\CashSessionResource;
use App\Modules\Caja\Models\SesionCaja;
use App\Modules\Caja\Services\CajaService;
use App\Modules\Sucursales\Support\SucursalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashSessionController extends Controller
{
    public function __construct(
        protected CajaService $service,
        protected SucursalContext $branchContext,
    ) {
    }

    public function current(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [SesionCaja::class, $this->branchContext->required()]);

        $session = $this->service->sesionAbierta($request->user(), $this->branchContext->required());

        return response()->json([
            'data' => $session ? new CashSessionResource($session) : null,
        ]);
    }

    public function open(OpenCashSessionRequest $request): JsonResponse
    {
        $this->authorize('open', [SesionCaja::class, $this->branchContext->required()]);

        $session = $this->service->abrir(
            $request->user(),
            $this->branchContext->required(),
            $request->integer('opening_amount'),
            $request->input('notes'),
            $request,
        );

        return (new CashSessionResource($session))->response()->setStatusCode(201);
    }

    public function close(CloseCashSessionRequest $request, SesionCaja $sesionCaja): JsonResponse
    {
        $this->authorize('close', [$sesionCaja, $this->branchContext->required()]);

        $session = $this->service->cerrar(
            $sesionCaja,
            $request->user(),
            $request->integer('counted_amount'),
            $request->input('notes'),
            $request,
        );

        return (new CashSessionResource($session))->response();
    }
}

<?php

namespace App\Modules\VentasPOS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Caja\Services\CajaService;
use App\Modules\Catalogo\Models\Servicio;
use App\Modules\VentasPOS\Http\Requests\StoreOrderRequest;
use App\Modules\VentasPOS\Http\Resources\OrderResource;
use App\Modules\VentasPOS\Models\Orden;
use App\Modules\VentasPOS\Services\OrderService;
use App\Modules\Sucursales\Support\SucursalContext;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $service,
        protected CajaService $cashService,
        protected SucursalContext $branchContext,
    ) {
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $branch = $this->branchContext->required();
        $this->authorize('create', [Orden::class, $branch]);
        $session = $this->cashService->sesionAbierta($request->user(), $branch);

        $order = $this->service->crear([
            'discount_amount' => $request->integer('discount_amount', 0),
            'notes' => $request->input('notes'),
            'items' => collect($request->validated('items'))->map(function (array $item) {
                $service = Servicio::query()->where('public_id', $item['servicio_public_id'])->firstOrFail();

                return [
                    'servicio_id' => $service->id,
                    'quantity' => $item['quantity'] ?? 1,
                ];
            })->all(),
        ], $branch, $request->user(), $session?->id, $request);

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function show(Orden $orden): JsonResponse
    {
        $this->authorize('view', [$orden, $this->branchContext->required()]);

        return (new OrderResource($orden->load(['cliente', 'detalles.servicio'])))->response();
    }
}

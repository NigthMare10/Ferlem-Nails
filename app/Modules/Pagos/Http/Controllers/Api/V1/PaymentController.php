<?php

namespace App\Modules\Pagos\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Caja\Services\CajaService;
use App\Modules\Pagos\Http\Requests\StorePaymentRequest;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Services\PaymentService;
use App\Modules\VentasPOS\Models\Orden;
use App\Modules\Sucursales\Support\SucursalContext;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $service,
        protected CajaService $cashService,
        protected SucursalContext $branchContext,
    ) {
    }

    public function store(StorePaymentRequest $request, Orden $orden): JsonResponse
    {
        abort_unless($orden->sucursal_id === $this->branchContext->id(), 404);

        $session = $this->cashService->sesionAbierta($request->user(), $this->branchContext->required());
        abort_unless($session !== null, 409, 'Debes abrir una sesión de caja antes de registrar pagos.');

        $payment = $this->service->registrar($orden, $request->validated(), $request->user(), $session, $request);

        return response()->json([
            'data' => [
                'public_id' => $payment->public_id,
                'status' => $payment->status,
                'amount' => $payment->amount,
            ],
        ], 201);
    }
}

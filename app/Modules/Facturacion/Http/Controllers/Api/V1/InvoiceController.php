<?php

namespace App\Modules\Facturacion\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Facturacion\Http\Resources\InvoiceResource;
use App\Modules\Facturacion\Models\Factura;
use App\Modules\Facturacion\Services\InvoiceService;
use App\Modules\Sucursales\Support\SucursalContext;
use App\Modules\VentasPOS\Models\Orden;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $service,
        protected SucursalContext $branchContext,
    ) {
    }

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', [Factura::class, $this->branchContext->required()]);

        $invoices = Factura::query()
            ->with(['cliente', 'detalles'])
            ->where('sucursal_id', $this->branchContext->id())
            ->latest('issued_at')
            ->paginate(15);

        return InvoiceResource::collection($invoices)->response();
    }

    public function store(Orden $orden): JsonResponse
    {
        $this->authorize('emit', [$orden, $this->branchContext->required()]);

        $invoice = $this->service->emitir($orden->load('detalles', 'cliente'), request()->user(), request());

        return (new InvoiceResource($invoice))->response()->setStatusCode(201);
    }

    public function show(Factura $factura): JsonResponse
    {
        $this->authorize('view', [$factura, $this->branchContext->required()]);

        return (new InvoiceResource($factura->load(['cliente', 'detalles'])))->response();
    }
}

<?php

namespace App\Modules\Facturacion\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Facturacion\Http\Resources\InvoiceResource;
use App\Modules\Facturacion\Models\Factura;
use App\Modules\Sucursales\Support\SucursalContext;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(protected SucursalContext $branchContext)
    {
    }

    public function index(): Response
    {
        $this->authorize('viewAny', [Factura::class, $this->branchContext->required()]);

        $invoices = Factura::query()
            ->with(['cliente'])
            ->where('sucursal_id', $this->branchContext->id())
            ->latest('issued_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Facturas/Index', [
            'facturas' => InvoiceResource::collection($invoices),
        ]);
    }

    public function show(Factura $factura): Response
    {
        $this->authorize('view', [$factura, $this->branchContext->required()]);

        return Inertia::render('Facturas/Show', [
            'factura' => new InvoiceResource($factura->load(['cliente', 'detalles'])),
        ]);
    }
}

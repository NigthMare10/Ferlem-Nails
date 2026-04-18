<?php

namespace App\Modules\VentasPOS\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Caja\Services\CajaService;
use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Catalogo\Services\CatalogoService;
use App\Modules\Facturacion\Services\InvoiceService;
use App\Modules\Pagos\Services\PaymentService;
use App\Modules\Sucursales\Support\SucursalContext;
use App\Modules\VentasPOS\Http\Requests\CheckoutPosRequest;
use App\Modules\VentasPOS\Services\OrderService;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class PosController extends Controller
{
    public function __construct(
        protected CatalogoService $catalogoService,
        protected OrderService $orderService,
        protected PaymentService $paymentService,
        protected InvoiceService $invoiceService,
        protected CajaService $cashService,
        protected SucursalContext $branchContext,
    ) {
    }

    public function index(): Response
    {
        return $this->renderPos();
    }

    public function detail(string $categoria): Response
    {
        abort_unless(in_array($categoria, ['unas', 'pestanas'], true), 404);

        return $this->renderPos($categoria);
    }

    protected function renderPos(?string $initialCategory = null): Response
    {
        $branch = $this->branchContext->required();
        $this->authorize('create', [\App\Modules\VentasPOS\Models\Orden::class, $branch]);
        $services = $this->catalogoService->listarActivosParaSucursal($branch)->map(function ($service) use ($branch) {
            $service->resolved_price = $this->catalogoService->precioVigente($service, $branch);

            return $service;
        });

        return Inertia::render('Pos/Index', [
            'sucursal' => [
                'name' => $branch->name,
                'code' => $branch->code,
                'terminal_id' => sprintf('%s-POS-01', $branch->code),
            ],
            'initialCategory' => $initialCategory,
            'servicios' => $services->map(fn ($service) => [
                'public_id' => $service->public_id,
                'name' => $service->name,
                'description' => $service->description,
                'duration_minutes' => $service->duration_minutes,
                'price_amount' => $service->resolved_price,
                'category_name' => $service->categoria?->name,
                'category_slug' => $service->categoria?->slug,
            ])->values(),
            'perfilOperativo' => [
                'name' => request()->user()->empleado?->name ?? request()->user()->name,
                'role_title' => request()->user()->empleado?->role_title,
            ],
        ]);
    }

    public function checkout(CheckoutPosRequest $request): RedirectResponse
    {
        $branch = $this->branchContext->required();
        $this->authorize('create', [\App\Modules\VentasPOS\Models\Orden::class, $branch]);
        $cashSession = $this->cashService->sesionAbierta($request->user(), $branch);

        $order = $this->orderService->crear([
            'discount_amount' => $request->integer('discount_amount', 0),
            'notes' => $request->input('notes'),
            'items' => collect($request->validated('items'))->map(function (array $item) {
                $service = Servicio::query()->where('public_id', $item['servicio_public_id'])->firstOrFail();

                return [
                    'servicio_id' => $service->id,
                    'quantity' => $item['quantity'] ?? 1,
                ];
            })->all(),
        ], $branch, $request->user(), $cashSession?->id, $request);

        $this->paymentService->registrar($order, [
            'method' => 'tarjeta_manual',
            'amount' => $order->total_amount,
            'reference' => $request->input('payment_reference'),
            'idempotency_key' => (string) str()->uuid(),
        ], $request->user(), $cashSession, $request);

        $invoice = $this->invoiceService->emitir($order->fresh('detalles'), $request->user(), $request);

        return redirect()->route('facturas.show', $invoice)->with('success', 'Cobro realizado correctamente.');
    }
}

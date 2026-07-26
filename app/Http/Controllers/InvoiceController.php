<?php

namespace App\Http\Controllers;

use App\Actions\Sales\AttachTransferProofAction;
use App\Actions\Sales\BuildInvoicesListAction;
use App\Actions\Sales\CancelSaleAction;
use App\Http\Requests\CancelSaleRequest;
use App\Http\Requests\InvoicesIndexRequest;
use App\Http\Requests\StoreInvoiceTransferProofRequest;
use App\Http\Resources\InvoiceDetailResource;
use App\Http\Resources\InvoiceListResource;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Support\Permissions;
use App\Support\SaleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(InvoicesIndexRequest $request, BuildInvoicesListAction $action): Response
    {
        $filters = $request->validated();
        unset($filters['page']);
        $canViewAll = $request->user()->can(Permissions::SALES_VIEW_ALL);

        return Inertia::render('Invoices/Index', [
            'invoices' => fn () => InvoiceListResource::collection($action->execute($request->user(), $filters)),
            'filters' => $filters,
            'canViewAll' => $canViewAll,
            'employees' => $canViewAll
                ? User::query()->orderBy('name')->get(['id', 'name'])->map->only(['id', 'name'])->values()
                : [],
        ]);
    }

    public function show(Request $request, Sale $sale): Response
    {
        abort_unless(SaleAccess::canView($request->user(), $sale), 403);
        $sale->load([
            'soldBy:id,name', 'canceledBy:id,name', 'appointment:id',
            'items.performedBy:id,name', 'payments',
        ]);

        return Inertia::render('Invoices/Show', [
            'invoice' => (new InvoiceDetailResource($sale))->resolve($request),
        ]);
    }

    public function cancel(
        CancelSaleRequest $request,
        Sale $sale,
        CancelSaleAction $action,
    ): RedirectResponse {
        abort_unless(SaleAccess::canView($request->user(), $sale), 403);
        $action->execute($request->user(), $sale, $request->string('cancellation_reason')->toString());

        return back(303)->with('success', 'La factura fue anulada correctamente.');
    }

    public function storeProof(
        StoreInvoiceTransferProofRequest $request,
        Sale $sale,
        SalePayment $payment,
        AttachTransferProofAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $sale, $payment, $request->file('payment_proof'));

        return back(303)->with('success', 'El comprobante de transferencia fue agregado.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Sales\CreateSaleAction;
use App\Http\Requests\CreateSaleRequest;
use App\Http\Resources\SaleReceiptResource;
use App\Http\Resources\SaleServiceResource;
use App\Models\Sale;
use App\Models\Service;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    public function create(Request $request): Response
    {
        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Sales/Create', [
            'services' => SaleServiceResource::collection($services)->resolve($request),
        ]);
    }

    public function store(CreateSaleRequest $request, CreateSaleAction $action): RedirectResponse
    {
        $data = $request->validated();
        $sale = $action->execute(
            $request->user(),
            $data['items'],
            $data['checkout_token'],
            $data['payment_method'],
        );

        return to_route('sales.receipt', $sale, 303);
    }

    public function receipt(Request $request, Sale $sale): Response
    {
        $user = $request->user();
        $canView = $user->hasRole('owner')
            || ($user->can(Permissions::SALES_VIEW_OWN) && $sale->sold_by === $user->getKey());

        abort_unless($canView, 403);

        $sale->load(['soldBy:id,name', 'items']);

        return Inertia::render('Sales/Receipt', [
            'sale' => (new SaleReceiptResource($sale))->resolve($request),
        ]);
    }
}

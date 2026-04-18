<?php

namespace App\Modules\Clientes\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Clientes\Http\Requests\StoreClientRequest;
use App\Modules\Clientes\Http\Requests\UpdateClientRequest;
use App\Modules\Clientes\Http\Resources\ClientResource;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Clientes\Services\ClienteService;
use App\Modules\Sucursales\Support\SucursalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function __construct(
        protected ClienteService $service,
        protected SucursalContext $branchContext,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', [Cliente::class, $this->branchContext->required()]);

        $clients = $this->service->paginarParaSucursal($this->branchContext->required(), $request->string('search')->toString() ?: null);

        return Inertia::render('Clientes/Index', [
            'filters' => ['search' => $request->string('search')->toString()],
            'clientes' => ClientResource::collection($clients),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', [Cliente::class, $this->branchContext->required()]);

        return Inertia::render('Clientes/Form', [
            'cliente' => null,
            'modo' => 'crear',
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $this->authorize('create', [Cliente::class, $this->branchContext->required()]);

        $this->service->store($request->validated(), $this->branchContext->required(), $request->user(), $request);

        return redirect()->route('clientes.index')->with('success', 'Cliente creado correctamente.');
    }

    public function edit(Cliente $cliente): Response
    {
        $this->authorize('view', [$cliente, $this->branchContext->required()]);

        return Inertia::render('Clientes/Form', [
            'cliente' => new ClientResource($cliente->load(['perfiles' => fn ($query) => $query->where('sucursal_id', $this->branchContext->id())])),
            'modo' => 'editar',
        ]);
    }

    public function update(UpdateClientRequest $request, Cliente $cliente): RedirectResponse
    {
        $this->authorize('update', [$cliente, $this->branchContext->required()]);

        $this->service->update($cliente, $request->validated(), $this->branchContext->required(), $request->user(), $request);

        return redirect()->route('clientes.index')->with('success', 'Cliente actualizado correctamente.');
    }
}

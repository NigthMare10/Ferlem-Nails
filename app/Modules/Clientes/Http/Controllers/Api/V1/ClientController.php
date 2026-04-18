<?php

namespace App\Modules\Clientes\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Clientes\Http\Requests\QuickStoreClientRequest;
use App\Modules\Clientes\Http\Requests\StoreClientRequest;
use App\Modules\Clientes\Http\Requests\UpdateClientRequest;
use App\Modules\Clientes\Http\Resources\ClientResource;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Clientes\Services\ClienteService;
use App\Modules\Sucursales\Support\SucursalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct(
        protected ClienteService $service,
        protected SucursalContext $branchContext,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [Cliente::class, $this->branchContext->required()]);

        $clients = $this->service->paginarParaSucursal(
            sucursal: $this->branchContext->required(),
            search: $request->string('search')->toString() ?: null,
            perPage: (int) $request->integer('per_page', 15),
        );

        return ClientResource::collection($clients)->response();
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $this->authorize('create', [Cliente::class, $this->branchContext->required()]);

        $client = $this->service->store($request->validated(), $this->branchContext->required(), $request->user(), $request);

        return (new ClientResource($client))->response()->setStatusCode(201);
    }

    public function quickStore(QuickStoreClientRequest $request): JsonResponse
    {
        $client = $this->service->altaRapida($request->validated(), $this->branchContext->required(), $request->user(), $request);

        return (new ClientResource($client))->response()->setStatusCode(201);
    }

    public function update(UpdateClientRequest $request, Cliente $cliente): JsonResponse
    {
        $this->authorize('update', [$cliente, $this->branchContext->required()]);

        $client = $this->service->update($cliente, $request->validated(), $this->branchContext->required(), $request->user(), $request);

        return (new ClientResource($client))->response();
    }
}

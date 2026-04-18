<?php

namespace App\Modules\Agenda\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Agenda\Http\Requests\StoreAppointmentRequest;
use App\Modules\Agenda\Http\Resources\AppointmentResource;
use App\Modules\Agenda\Models\Cita;
use App\Modules\Agenda\Services\AgendaService;
use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Clientes\Models\PerfilCliente;
use App\Modules\Empleados\Models\Empleado;
use App\Modules\Sucursales\Support\SucursalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(
        protected AgendaService $service,
        protected SucursalContext $branchContext,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [Cita::class, $this->branchContext->required()]);

        $appointments = Cita::query()
            ->with(['cliente', 'servicio', 'empleado'])
            ->where('sucursal_id', $this->branchContext->id())
            ->orderBy('scheduled_start')
            ->paginate(15)
            ->withQueryString();

        return AppointmentResource::collection($appointments)->response();
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $branch = $this->branchContext->required();
        $this->authorize('create', [Cita::class, $branch]);
        $client = Cliente::query()->where('public_id', $request->string('cliente_public_id'))->firstOrFail();
        abort_unless($client->perfiles()->where('sucursal_id', $branch->id)->exists(), 404);
        $service = Servicio::query()->where('public_id', $request->string('servicio_public_id'))->firstOrFail();
        $employee = $request->filled('empleado_public_id')
            ? Empleado::query()->where('public_id', $request->string('empleado_public_id'))->firstOrFail()
            : null;
        $profile = PerfilCliente::query()->where('cliente_id', $client->id)->where('sucursal_id', $branch->id)->first();

        $appointment = $this->service->crear([
            'cliente_id' => $client->id,
            'perfil_cliente_id' => $profile?->id,
            'empleado_id' => $employee?->id,
            'servicio_id' => $service->id,
            'scheduled_start' => $request->date('scheduled_start'),
            'scheduled_end' => $request->date('scheduled_end'),
            'status' => $request->input('status', 'programada'),
            'notes' => $request->input('notes'),
        ], $branch, $request->user(), $request);

        return (new AppointmentResource($appointment->load(['cliente', 'servicio', 'empleado'])))
            ->response()
            ->setStatusCode(201);
    }
}

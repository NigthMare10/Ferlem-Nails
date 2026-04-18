<?php

namespace App\Modules\Agenda\Http\Controllers\Web;

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
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AgendaController extends Controller
{
    public function __construct(
        protected AgendaService $service,
        protected SucursalContext $branchContext,
    ) {
    }

    public function index(): Response
    {
        $branchId = $this->branchContext->id();
        $this->authorize('viewAny', [Cita::class, $this->branchContext->required()]);

        return Inertia::render('Agenda/Index', [
            'citas' => AppointmentResource::collection(
                Cita::query()->with(['cliente', 'servicio', 'empleado'])->where('sucursal_id', $branchId)->latest('scheduled_start')->paginate(10)
            ),
            'clientes' => Cliente::query()
                ->whereHas('perfiles', fn ($query) => $query->where('sucursal_id', $branchId))
                ->orderBy('name')
                ->get(['id', 'public_id', 'name']),
            'servicios' => Servicio::query()->where('is_active', true)->orderBy('name')->get(['id', 'public_id', 'name']),
            'empleados' => Empleado::query()
                ->whereHas('sucursales', fn ($query) => $query->where('sucursal_id', $branchId))
                ->orderBy('name')
                ->get(['id', 'public_id', 'name']),
        ]);
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $branch = $this->branchContext->required();
        $this->authorize('create', [Cita::class, $branch]);
        $client = Cliente::query()->where('public_id', $request->string('cliente_public_id'))->firstOrFail();
        abort_unless($client->perfiles()->where('sucursal_id', $branch->id)->exists(), 404);
        $service = Servicio::query()->where('public_id', $request->string('servicio_public_id'))->firstOrFail();
        $employee = $request->filled('empleado_public_id') ? Empleado::query()->where('public_id', $request->string('empleado_public_id'))->firstOrFail() : null;
        $profile = PerfilCliente::query()->where('cliente_id', $client->id)->where('sucursal_id', $branch->id)->first();

        $this->service->crear([
            'cliente_id' => $client->id,
            'perfil_cliente_id' => $profile?->id,
            'empleado_id' => $employee?->id,
            'servicio_id' => $service->id,
            'scheduled_start' => $request->date('scheduled_start'),
            'scheduled_end' => $request->date('scheduled_end'),
            'status' => $request->input('status', 'programada'),
            'notes' => $request->input('notes'),
        ], $branch, $request->user(), $request);

        return redirect()->route('agenda.index')->with('success', 'Cita creada correctamente.');
    }
}

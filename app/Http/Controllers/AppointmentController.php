<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Actions\Appointments\CancelAppointmentAction;
use App\Actions\Appointments\BuildAppointmentAvailabilityAction;
use App\Actions\Appointments\BuildAppointmentCalendarAction;
use App\Actions\Appointments\MarkAppointmentNoShowAction;
use App\Actions\Appointments\RescheduleAppointmentAction;
use App\Actions\Appointments\UpdateAppointmentAction;
use App\Http\Requests\AppointmentsIndexRequest;
use App\Http\Requests\AppointmentAvailabilityRequest;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\MarkAppointmentNoShowRequest;
use App\Http\Requests\RescheduleAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentDetailsResource;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\SaleServiceResource;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Service;
use App\Models\User;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(AppointmentsIndexRequest $request, BuildAppointmentCalendarAction $calendar): Response
    {
        $user = $request->user();
        $data = $request->validated();
        $date = ($data['date'] ?? null)
            ?? CarbonImmutable::now(CreateAppointmentAction::TIMEZONE)->format('Y-m-d');
        $month = ($data['month'] ?? null) ?? substr($date, 0, 7);
        $view = ($data['view'] ?? null) ?? 'month';
        $employeeId = isset($data['employee_id']) ? (int) $data['employee_id'] : null;
        $appointments = collect();
        if ($view === 'day') {
            $localStart = CarbonImmutable::createFromFormat('!Y-m-d', $date, CreateAppointmentAction::TIMEZONE);
            $localEnd = $localStart->addDay();
            $appointments = Appointment::query()
                ->with(['assignedTo:id,name', 'items.assignedTo:id,name'])
                ->where('status', Appointment::STATUS_SCHEDULED)
                ->where('scheduled_start', '>=', $localStart->utc())
                ->where('scheduled_start', '<', $localEnd->utc())
                ->when(
                    ! $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL),
                    fn ($query) => $query->whereHas('items', fn ($items) => $items->where('assigned_to', $user->getKey())),
                )
                ->when($employeeId && $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL), fn ($query) => $query->whereHas('items', fn ($items) => $items->where('assigned_to', $employeeId)))
                ->orderBy('scheduled_start')
                ->orderBy('id')
                ->get();
        }

        $assignees = User::query()
            ->where('is_active', true)
            ->permission(Permissions::APPOINTMENTS_PERFORM)
            ->when(
                ! $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL),
                fn ($query) => $query->whereKey($user->getKey()),
            )
            ->orderBy('name')
            ->get(['id', 'name']);

        $services = Service::query()->where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Appointments/Index', [
            'date' => $date,
            'month' => $month,
            'view' => $view,
            'today' => CarbonImmutable::now(CreateAppointmentAction::TIMEZONE)->format('Y-m-d'),
            'employee_id' => $employeeId,
            'timezone' => CreateAppointmentAction::TIMEZONE,
            'appointments' => AppointmentResource::collection($appointments)->resolve($request),
            'calendar_days' => $view === 'month' ? $calendar->execute($user, $month, $employeeId) : [],
            'assignees' => $assignees->map->only(['id', 'name'])->values(),
            'services' => SaleServiceResource::collection($services)->resolve($request),
        ]);
    }

    public function store(StoreAppointmentRequest $request, CreateAppointmentAction $action): RedirectResponse
    {
        $action->execute($request->user(), $request->validated());

        return to_route('appointments.index', ['date' => $request->validated('date')], 303)
            ->with('success', 'La cita fue creada correctamente.');
    }

    public function availability(AppointmentAvailabilityRequest $request, BuildAppointmentAvailabilityAction $action): JsonResponse
    {
        $data = $request->validated();
        $appointment = ($data['appointment_id'] ?? null)
            ? Appointment::query()->with(['items' => fn ($query) => $query->orderBy('position')->orderBy('id')])->findOrFail($data['appointment_id'])
            : null;
        if ($appointment) {
            $this->authorizeView($request->user(), $appointment);
        }

        $items = $appointment
            ? $this->availabilityItems($request->user(), $appointment, $data['assignments'] ?? [])
            : $data['items'];

        return response()->json($action->execute(
            $request->user(),
            $data['date'],
            $items,
            $appointment?->getKey(),
        ));
    }

    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeView($request->user(), $appointment);
        $appointment->load(['assignedTo:id,name', 'createdBy:id,name', 'canceledBy:id,name', 'noShowBy:id,name', 'items.assignedTo:id,name', 'events.performedBy:id,name']);

        return response()->json([
            'appointment' => (new AppointmentDetailsResource($appointment))->resolve($request),
        ]);
    }

    public function update(
        UpdateAppointmentRequest $request,
        Appointment $appointment,
        UpdateAppointmentAction $action,
    ): RedirectResponse {
        $updated = $action->execute($request->user(), $appointment, $request->validated());

        return to_route('appointments.index', [
            'date' => $updated->scheduled_start->setTimezone(CreateAppointmentAction::TIMEZONE)->format('Y-m-d'),
        ], 303)->with('success', 'La cita fue actualizada correctamente.');
    }

    public function reschedule(
        RescheduleAppointmentRequest $request,
        Appointment $appointment,
        RescheduleAppointmentAction $action,
    ): RedirectResponse {
        $updated = $action->execute($request->user(), $appointment, $request->validated());

        return to_route('appointments.index', [
            'date' => $updated->scheduled_start->setTimezone(CreateAppointmentAction::TIMEZONE)->format('Y-m-d'),
        ], 303)->with('success', 'La cita fue reprogramada correctamente.');
    }

    public function cancel(
        CancelAppointmentRequest $request,
        Appointment $appointment,
        CancelAppointmentAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $appointment, $request->validated('reason'));

        return back(303)->with('success', 'La cita fue cancelada correctamente.');
    }

    public function markNoShow(
        MarkAppointmentNoShowRequest $request,
        Appointment $appointment,
        MarkAppointmentNoShowAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $appointment, $request->validated('reason'));

        return back(303)->with('success', 'La cita fue marcada como No llegó.');
    }

    private function authorizeView(User $user, Appointment $appointment): void
    {
        $canView = $user->is_active
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS)
            && ($user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL)
                || ($user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_OWN)
                    && $appointment->items()->where('assigned_to', $user->getKey())->exists()));

        abort_unless($canView, 403);
    }

    private function availabilityItems(User $user, Appointment $appointment, array $requestedAssignments): array
    {
        if ($appointment->status !== Appointment::STATUS_SCHEDULED) {
            throw ValidationException::withMessages(['appointment' => 'Solo las citas programadas pueden consultar horarios para reprogramación.']);
        }

        if (! $user->hasPermissionTo(Permissions::APPOINTMENTS_ASSIGN)
            && $appointment->items->contains(fn (AppointmentItem $item) => $item->assigned_to !== $user->getKey())) {
            throw ValidationException::withMessages([
                'appointment' => 'Esta cita incluye servicios de otras personas. Solicita a un responsable que la reprograme.',
            ]);
        }

        $assignments = collect($requestedAssignments)->keyBy('appointment_item_id');
        if ($assignments->keys()->diff($appointment->items->pluck('id'))->isNotEmpty()) {
            throw ValidationException::withMessages(['assignments' => 'Una asignación no pertenece a esta cita.']);
        }
        if ($assignments->isNotEmpty() && ! $user->hasPermissionTo(Permissions::APPOINTMENTS_ASSIGN)) {
            throw ValidationException::withMessages(['assignments' => 'No tienes permiso para cambiar el personal asignado.']);
        }

        $items = $appointment->items->map(function (AppointmentItem $item) use ($assignments) {
            return [
                'assigned_to' => (int) ($assignments->get($item->id)['assigned_to'] ?? $item->assigned_to),
                'quantity' => $item->quantity,
                'duration_minutes' => $item->duration_minutes,
            ];
        })->values()->all();

        $assignees = User::query()
            ->whereKey(collect($items)->pluck('assigned_to')->unique()->all())
            ->where('is_active', true)
            ->permission(Permissions::APPOINTMENTS_PERFORM)
            ->pluck('id');
        if ($assignees->count() !== collect($items)->pluck('assigned_to')->unique()->count()) {
            throw ValidationException::withMessages(['assignments' => 'Una persona seleccionada no está disponible para realizar citas.']);
        }

        return $items;
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Sales\CreateSaleAction;
use App\Http\Requests\CreateSaleRequest;
use App\Http\Resources\SaleReceiptResource;
use App\Http\Resources\SaleServiceResource;
use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

        $appointmentContext = null;
        if ($request->query->has('appointment')) {
            $appointment = Appointment::query()
                ->with(['items.assignedTo:id,name', 'deposit', 'sale'])
                ->findOrFail((int) $request->query('appointment'));
            $this->authorizeAppointmentCheckout($request->user(), $appointment);
            if ($appointment->status !== Appointment::STATUS_SCHEDULED) {
                throw ValidationException::withMessages([
                    'appointment' => 'Solo una cita programada puede atenderse y cobrarse.',
                ]);
            }
            if ($appointment->sale) {
                throw ValidationException::withMessages([
                    'appointment' => "Esta cita ya fue convertida en la venta {$appointment->sale->sale_number}.",
                ]);
            }

            $deposit = $appointment->deposit?->status === AppointmentDeposit::STATUS_PENDING
                ? $appointment->deposit
                : null;
            $depositCents = $deposit?->availableAmountCents() ?? 0;
            $appointmentContext = [
                'id' => $appointment->id,
                'client_name' => $appointment->client_name,
                'client_phone' => $appointment->client_phone,
                'scheduled_start' => $appointment->scheduled_start->toIso8601String(),
                'scheduled_end' => $appointment->scheduled_end->toIso8601String(),
                'reserved_duration_minutes' => $appointment->expected_duration_minutes,
                'reserved_total' => $appointment->expected_total,
                'deposit' => $deposit ? [
                    'id' => $deposit->id,
                    'amount' => $deposit->amount,
                    'available_amount' => $deposit->availableAmount(),
                    'payment_method' => $deposit->payment_method,
                    'payment_method_label' => $deposit->payment_method === Sale::PAYMENT_METHOD_CARD ? 'Tarjeta' : 'Efectivo',
                ] : null,
                'pending_balance' => Money::fromCents(max(0, Money::toCents($appointment->expected_total) - $depositCents)),
                'can_assign' => $request->user()->hasPermissionTo(Permissions::APPOINTMENTS_ASSIGN),
                'can_resolve_deposit' => $request->user()->hasPermissionTo(Permissions::APPOINTMENTS_RESOLVE_DEPOSIT),
                'items' => $appointment->items->sortBy('position')->values()->map(fn ($item) => [
                    'appointment_item_id' => $item->id,
                    'service_id' => $item->service_id,
                    'name' => $item->service_name,
                    'description' => $item->service_description,
                    'duration_minutes' => $item->duration_minutes,
                    'price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'position' => $item->position,
                    'performed_by' => ['id' => $item->assignedTo->id, 'name' => $item->assignedTo->name],
                    'reserved' => true,
                ])->all(),
            ];
        }

        $assignees = $appointmentContext && $request->user()->hasPermissionTo(Permissions::APPOINTMENTS_ASSIGN)
            ? User::query()->where('is_active', true)->permission(Permissions::APPOINTMENTS_PERFORM)->orderBy('name')->get(['id', 'name'])
            : collect();

        return Inertia::render('Sales/Create', [
            'services' => SaleServiceResource::collection($services)->resolve($request),
            'appointment' => $appointmentContext,
            'assignees' => $assignees->map->only(['id', 'name'])->values(),
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

        $sale->load(['soldBy:id,name', 'appointment', 'items.performedBy:id,name', 'payments']);

        return Inertia::render('Sales/Receipt', [
            'sale' => (new SaleReceiptResource($sale))->resolve($request),
        ]);
    }

    private function authorizeAppointmentCheckout(User $user, Appointment $appointment): void
    {
        $canView = $user->is_active
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS)
            && $user->hasPermissionTo(Permissions::APPOINTMENTS_CONVERT_TO_SALE)
            && $user->hasPermissionTo(Permissions::SALES_ACCESS)
            && $user->hasPermissionTo(Permissions::SALES_CREATE)
            && ($user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL)
                || ($user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_OWN)
                    && $appointment->items->contains(fn ($item) => $item->assigned_to === $user->getKey())));

        abort_unless($canView, 403);
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Sales\CancelSaleAction;
use App\Actions\Sales\CreateSaleAction;
use App\Http\Requests\CancelSaleRequest;
use App\Http\Requests\CreateSaleRequest;
use App\Http\Resources\SaleReceiptResource;
use App\Http\Resources\SaleServiceResource;
use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\User;
use App\Support\Money;
use App\Support\Permissions;
use App\Support\SaleAccess;
use App\Support\TransferProofStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesController extends Controller
{
    private ?bool $discountPermissionExists = null;

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
                    'card_fee_amount' => $deposit->card_fee_amount,
                ] : null,
                'pending_balance' => Money::fromCents(max(0, Money::toCents($appointment->expected_total) - $depositCents)),
                'can_assign' => $request->user()->hasPermissionTo(Permissions::APPOINTMENTS_ASSIGN),
                'can_resolve_deposit' => $request->user()->hasPermissionTo(Permissions::APPOINTMENTS_RESOLVE_DEPOSIT),
                'can_apply_discount' => $this->canApplyDiscount($request->user()),
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
            'canApplyDiscount' => $this->canApplyDiscount($request->user()),
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
            $data['payment_proof'] ?? null,
            $data['client_name'] ?? null,
            $data['additional_charges'] ?? [],
            $data['discount_amount'] ?? null,
        );

        return to_route('sales.receipt', $sale, 303);
    }

    public function receipt(Request $request, Sale $sale): Response
    {
        $user = $request->user();
        abort_unless($user->can(Permissions::SALES_REPRINT) && SaleAccess::canView($user, $sale), 403);

        $sale->load(['soldBy:id,name', 'canceledBy:id,name', 'appointment', 'items.performedBy:id,name', 'additionalCharges', 'payments']);

        return Inertia::render('Sales/Receipt', [
            'sale' => (new SaleReceiptResource($sale))->resolve($request),
        ]);
    }

    public function cancel(CancelSaleRequest $request, Sale $sale, CancelSaleAction $action): RedirectResponse
    {
        $action->execute($request->user(), $sale, $request->string('cancellation_reason')->toString());

        return to_route('sales.receipt', $sale, 303)->with('success', 'La venta fue anulada correctamente.');
    }

    public function proof(Request $request, Sale $sale, SalePayment $payment): StreamedResponse
    {
        abort_unless($request->user()->can(Permissions::SALES_VIEW_TRANSFER_PROOF), 403);
        abort_unless(SaleAccess::canView($request->user(), $sale), 403);
        abort_unless($payment->sale_id === $sale->getKey(), 404);
        abort_unless($payment->method === Sale::PAYMENT_METHOD_TRANSFER && $payment->proof_path, 404);
        abort_unless((bool) preg_match('/^\d{4}\/\d{2}\/[a-f0-9]{48}\.(jpg|png|webp)$/', $payment->proof_path), 404);

        $disk = Storage::disk(TransferProofStorage::DISK);
        abort_unless($disk->exists($payment->proof_path), 404);
        $stream = $disk->readStream($payment->proof_path);
        abort_if($stream === false, 404);
        $disposition = HeaderUtils::makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $payment->proof_original_name ?: 'comprobante-transferencia',
            'comprobante-transferencia',
        );

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $payment->proof_mime,
            'Content-Disposition' => $disposition,
            'Content-Length' => (string) $payment->proof_size,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
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

    private function canApplyDiscount(User $user): bool
    {
        if ($this->discountPermissionExists === null) {
            $this->discountPermissionExists = Permission::query()
                ->where('name', Permissions::SALES_APPLY_FREQUENT_DISCOUNT)
                ->where('guard_name', 'web')
                ->exists();

            if (! $this->discountPermissionExists) {
                Log::warning('Discount permission is missing; discount controls were disabled.');
            }
        }

        if (! $this->discountPermissionExists) {
            return false;
        }

        return $user->hasPermissionTo(Permissions::SALES_APPLY_FREQUENT_DISCOUNT)
            && $user->hasAnyRole(['owner', 'administrator']);
    }
}

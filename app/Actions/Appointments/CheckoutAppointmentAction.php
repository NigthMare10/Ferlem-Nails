<?php

namespace App\Actions\Appointments;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Actions\Sales\PersistCompletedSaleAction;
use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Models\AppointmentEvent;
use App\Models\AppointmentItem;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\User;
use App\Support\AppointmentCheckoutWindow;
use App\Support\Money;
use App\Support\Permissions;
use App\Support\SaleAdditionalCharges;
use App\Support\SaleFinancials;
use App\Support\TransferProofStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutAppointmentAction
{
    private const MAX_AMOUNT_CENTS = 999999999999;

    public function __construct(
        private readonly PersistCompletedSaleAction $persistCompletedSale,
        private readonly PublishInternalNotificationAction $publishNotification,
        private readonly TransferProofStorage $proofStorage,
    ) {}

    public function execute(User $user, Appointment $appointment, array $data): Sale
    {
        $this->authorizeGlobal($user);
        $data['additional_charges'] = $this->normalizeCharges($data['additional_charges'] ?? []);
        $requestHash = $this->requestHash($appointment->getKey(), $data);

        if ($existing = $this->findByToken($data['checkout_token'])) {
            return $this->resolveExisting($existing, $user, $appointment, $requestHash);
        }

        $proof = isset($data['payment_proof']) ? $this->storeProof($data['payment_proof'], $user) : null;

        try {
            $sale = DB::transaction(function () use ($user, $appointment, $data, $requestHash, $proof) {
                if ($existing = $this->findByToken($data['checkout_token'])) {
                    return $this->resolveExisting($existing, $user, $appointment, $requestHash);
                }

                $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
                $originalItems = $locked->items()->orderBy('position')->orderBy('id')->lockForUpdate()->get();
                $locked->setRelation('items', $originalItems);
                $deposit = $locked->deposit()->lockForUpdate()->first();

                $this->authorizeScope($user, $locked);
                if ($sale = Sale::query()->where('appointment_id', $locked->getKey())->first()) {
                    throw ValidationException::withMessages(['appointment' => "Esta cita ya fue convertida en la venta {$sale->sale_number}."]);
                }
                if ($locked->status !== Appointment::STATUS_SCHEDULED) {
                    throw ValidationException::withMessages(['appointment' => $locked->status === Appointment::STATUS_NO_SHOW
                        && $locked->no_show_reason === 'Marcada automáticamente al vencer el tiempo disponible para cobrar.'
                        ? 'El tiempo disponible para cobrar esta cita ya venció y fue marcada como No llegó.'
                        : 'Solo una cita programada puede atenderse y cobrarse.']);
                }
                if (! AppointmentCheckoutWindow::canCheckout($locked, now(CreateAppointmentAction::TIMEZONE)->toImmutable(), $originalItems)) {
                    throw ValidationException::withMessages(['appointment' => 'El tiempo disponible para cobrar esta cita ya venció y fue marcada como No llegó.']);
                }

                $prepared = $this->prepareItems($user, $originalItems, $data);
                $charges = $data['additional_charges'];
                $discountCents = Money::toCents($data['discount_amount'] ?? '0.00');
                $this->authorizeDiscount($user, $discountCents);
                $totalCents = collect($prepared)->sum('line_total_cents') + array_sum(array_column($charges, 'amount_cents')) - $discountCents;
                if ($totalCents > self::MAX_AMOUNT_CENTS) {
                    throw ValidationException::withMessages(['items' => 'El total de la venta excede el monto permitido.']);
                }

                $depositCents = $deposit?->status === AppointmentDeposit::STATUS_PENDING
                    ? $deposit->availableAmountCents()
                    : 0;
                if ($totalCents < $depositCents) {
                    throw ValidationException::withMessages([
                        'items' => 'El total de los servicios realizados no puede ser menor que el adelanto. Ajusta los servicios o el monto antes de completar la cita.',
                    ]);
                }

                $balanceCents = $totalCents - $depositCents;
                $depositFeeCents = $depositCents > 0 ? Money::toCents($deposit->card_fee_amount) : 0;
                $payments = [];
                if ($depositCents > 0 && $deposit) {
                    $payments[] = SaleFinancials::payment(SalePayment::TYPE_DEPOSIT_APPLIED, $deposit->payment_method, $depositCents, $depositFeeCents, $deposit->card_fee_rate, $deposit->getKey());
                }
                if ($balanceCents > 0) {
                    $finalPayment = SaleFinancials::payment(SalePayment::TYPE_FINAL_PAYMENT, $data['payment_method'], $balanceCents);
                    $payments[] = [...$finalPayment, ...($proof ?? [])];
                } elseif ($proof) {
                    throw ValidationException::withMessages(['payment_proof' => 'No se puede adjuntar una captura porque el adelanto cubre todo el saldo.']);
                }
                $sale = $this->persistCompletedSale->execute(
                    $user,
                    $prepared,
                    $payments,
                    $data['checkout_token'],
                    $requestHash,
                    $locked->getKey(),
                    $locked->client_name,
                    $charges,
                    $discountCents,
                );

                if ($depositCents > 0 && $deposit) {
                    $deposit->status = AppointmentDeposit::STATUS_APPLIED;
                    $deposit->applied_amount = Money::fromCents(Money::toCents($deposit->applied_amount) + $depositCents);
                    $deposit->resolved_at = now('UTC');
                    $deposit->resolved_by = $user->getKey();
                    $deposit->resolution_notes = "Aplicado a la venta {$sale->sale_number}.";
                    $deposit->save();
                }

                $locked->status = Appointment::STATUS_COMPLETED;
                $locked->completed_at = now('UTC');
                $locked->save();

                $event = new AppointmentEvent;
                $event->appointment_id = $locked->getKey();
                $event->type = AppointmentEvent::TYPE_COMPLETED;
                $event->performed_by = $user->getKey();
                $event->occurred_at = now('UTC');
                $event->previous_values = ['status' => Appointment::STATUS_SCHEDULED];
                $event->new_values = ['status' => Appointment::STATUS_COMPLETED, 'sale_id' => $sale->getKey(), 'sale_number' => $sale->sale_number];
                $event->notes = 'Cita atendida y cobrada.';
                $event->save();

                $this->publishNotification->execute(
                    $user,
                    'appointment.completed',
                    'Cita completada',
                    "Se completó la cita de {$locked->client_name}.",
                    "/appointments?appointment={$locked->getKey()}",
                    ['type' => 'appointment', 'id' => $locked->getKey(), 'sale_id' => $sale->getKey()],
                    "appointment-event:{$event->getKey()}",
                    $event->occurred_at,
                );

                return $sale->load(['soldBy:id,name', 'appointment', 'items.performedBy:id,name', 'payments']);
            }, 3);

            if ($proof && ! $sale->payments->contains('proof_path', $proof['proof_path'])) {
                $this->proofStorage->delete($proof);
            }

            return $sale;
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->findByToken($data['checkout_token']);
            if ($existing) {
                $sale = $this->resolveExisting($existing, $user, $appointment, $requestHash);
                $this->proofStorage->delete($proof);

                return $sale;
            }
            if ($sale = Sale::query()->useWritePdo()->where('appointment_id', $appointment->getKey())->first()) {
                $this->proofStorage->delete($proof);
                throw ValidationException::withMessages(['appointment' => "Esta cita ya fue convertida en la venta {$sale->sale_number}."]);
            }

            $this->proofStorage->delete($proof);

            throw $exception;
        } catch (Throwable $exception) {
            $this->proofStorage->delete($proof);

            throw $exception;
        }
    }

    private function storeProof($file, User $user): array
    {
        try {
            return $this->proofStorage->store($file, $user);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'payment_proof' => 'No se pudo guardar la captura. Inténtalo nuevamente sin perder el cobro.',
            ]);
        }
    }

    private function prepareItems(User $user, Collection $originalItems, array $data): array
    {
        $originalById = $originalItems->keyBy('id');
        $removed = collect($data['removed_appointment_item_ids'])->map(fn ($id) => (int) $id);
        if ($removed->diff($originalById->keys())->isNotEmpty()) {
            throw ValidationException::withMessages(['removed_appointment_item_ids' => 'Un servicio retirado no pertenece a esta cita.']);
        }

        $submittedReserved = collect($data['items'])->pluck('appointment_item_id')->filter()->map(fn ($id) => (int) $id);
        if ($submittedReserved->duplicates()->isNotEmpty() || $submittedReserved->diff($originalById->keys())->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'Una línea reservada no es válida para esta cita.']);
        }
        if ($submittedReserved->intersect($removed)->isNotEmpty()
            || $originalById->keys()->diff($submittedReserved->merge($removed))->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'Confirma explícitamente cualquier servicio reservado que no se realizó.']);
        }

        $additionalIds = collect($data['items'])->filter(fn ($line) => empty($line['appointment_item_id']))
            ->pluck('service_id')->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values();
        $submittedAdditionalIds = collect($data['items'])->filter(fn ($line) => empty($line['appointment_item_id']))
            ->pluck('service_id')->filter()->map(fn ($id) => (int) $id);
        $services = Service::query()->whereKey($additionalIds->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($services->count() !== $additionalIds->count() || $services->contains(fn (Service $service) => ! $service->is_active)) {
            throw ValidationException::withMessages(['items' => 'Uno de los servicios adicionales ya no está disponible.']);
        }

        $canAssign = $user->hasPermissionTo(Permissions::APPOINTMENTS_ASSIGN);
        $performerIds = collect($data['items'])->pluck('performed_by')->map(fn ($id) => (int) $id)->unique()->sort()->values();
        $performers = User::query()->whereKey($performerIds->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($performerIds as $id) {
            $performer = $performers->get($id);
            if (! $performer || ! $performer->is_active || ! $performer->hasPermissionTo(Permissions::APPOINTMENTS_PERFORM)) {
                throw ValidationException::withMessages(['items' => 'Una persona seleccionada no está disponible para realizar servicios.']);
            }
        }

        $prepared = [];
        foreach (array_values($data['items']) as $line) {
            $quantity = (int) $line['quantity'];
            $performerId = (int) $line['performed_by'];
            $appointmentItem = ! empty($line['appointment_item_id']) ? $originalById->get((int) $line['appointment_item_id']) : null;
            if (! $appointmentItem && empty($line['service_id'])) {
                throw ValidationException::withMessages(['items' => 'Cada servicio adicional debe identificar un servicio vigente.']);
            }
            if (! $canAssign) {
                $expectedPerformer = $appointmentItem?->assigned_to ?? $user->getKey();
                if ($performerId !== $expectedPerformer) {
                    throw new AuthorizationException('No tienes permiso para asignar servicios a otra persona.');
                }
            }

            $service = $appointmentItem ? null : $services->get((int) $line['service_id']);
            $unitPriceCents = Money::toCents($appointmentItem?->unit_price ?? $service->price);
            $lineTotalCents = $unitPriceCents * $quantity;
            if ($lineTotalCents > self::MAX_AMOUNT_CENTS) {
                throw ValidationException::withMessages(['items' => 'Una línea excede el monto permitido.']);
            }
            $prepared[] = [
                'appointment_item_id' => $appointmentItem?->getKey(),
                'service_id' => $appointmentItem ? null : $service->getKey(),
                'performed_by' => $performerId,
                'service_name' => $appointmentItem?->service_name ?? $service->name,
                'service_description' => $appointmentItem?->service_description ?? $service->description,
                'duration_minutes' => $appointmentItem?->duration_minutes ?? $service->duration_minutes,
                'unit_price_cents' => $unitPriceCents,
                'quantity' => $quantity,
                'line_total_cents' => $lineTotalCents,
            ];
        }

        return $prepared;
    }

    private function authorizeGlobal(User $user): void
    {
        if (! $user->is_active
            || ! $user->hasPermissionTo(Permissions::APPOINTMENTS_ACCESS)
            || ! $user->hasPermissionTo(Permissions::APPOINTMENTS_CONVERT_TO_SALE)
            || ! $user->hasPermissionTo(Permissions::SALES_ACCESS)
            || ! $user->hasPermissionTo(Permissions::SALES_CREATE)) {
            throw new AuthorizationException;
        }
    }

    private function authorizeScope(User $user, Appointment $appointment): void
    {
        if ($user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_ALL)) {
            return;
        }
        if (! $user->hasPermissionTo(Permissions::APPOINTMENTS_VIEW_OWN)
            || ! $appointment->items->contains(fn (AppointmentItem $item) => $item->assigned_to === $user->getKey())) {
            throw new AuthorizationException;
        }
    }

    private function requestHash(int $appointmentId, array $data): string
    {
        $items = array_map(fn ($item) => [
            'appointment_item_id' => isset($item['appointment_item_id']) ? (int) $item['appointment_item_id'] : null,
            'service_id' => isset($item['service_id']) ? (int) $item['service_id'] : null,
            'quantity' => (int) $item['quantity'],
            'performed_by' => (int) $item['performed_by'],
        ], array_values($data['items']));
        $removed = array_map('intval', $data['removed_appointment_item_ids']);
        sort($removed);

        return hash('sha256', json_encode([
            'appointment_id' => $appointmentId,
            'payment_method' => $data['payment_method'],
            'items' => $items,
            'removed_appointment_item_ids' => $removed,
            'additional_charges' => $data['additional_charges'] ?? [],
            'discount_amount' => $data['discount_amount'] ?? null,
        ], JSON_THROW_ON_ERROR));
    }

    private function findByToken(string $token): ?Sale
    {
        return Sale::query()->useWritePdo()->where('checkout_token', $token)->first();
    }

    private function resolveExisting(Sale $sale, User $user, Appointment $appointment, string $hash): Sale
    {
        if ($sale->sold_by !== $user->getKey() || $sale->appointment_id !== $appointment->getKey()) {
            throw new AuthorizationException;
        }
        if (! hash_equals($sale->request_hash, $hash)) {
            throw ValidationException::withMessages(['checkout_token' => 'Esta confirmación ya fue utilizada con otra cita, selección o forma de pago.']);
        }

        return $sale->loadMissing(['soldBy:id,name', 'appointment', 'items.performedBy:id,name', 'additionalCharges', 'payments']);
    }

    private function normalizeCharges(array $charges): array
    {
        return SaleAdditionalCharges::normalize($charges);
    }

    private function authorizeDiscount(User $user, int $discountCents): void
    {
        if ($discountCents > 0 && ! $user->hasPermissionTo(Permissions::SALES_APPLY_FREQUENT_DISCOUNT)) {
            throw new AuthorizationException('No tienes permiso para aplicar descuentos.');
        }
    }
}

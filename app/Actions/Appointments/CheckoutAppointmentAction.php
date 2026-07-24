<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Models\AppointmentEvent;
use App\Models\AppointmentItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\User;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutAppointmentAction
{
    private const MAX_AMOUNT_CENTS = 999999999999;

    public function execute(User $user, Appointment $appointment, array $data): Sale
    {
        $this->authorizeGlobal($user);
        $requestHash = $this->requestHash($appointment->getKey(), $data);

        if ($existing = $this->findByToken($data['checkout_token'])) {
            return $this->resolveExisting($existing, $user, $appointment, $requestHash);
        }

        try {
            return DB::transaction(function () use ($user, $appointment, $data, $requestHash) {
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
                    throw ValidationException::withMessages(['appointment' => 'Solo una cita programada puede atenderse y cobrarse.']);
                }

                $prepared = $this->prepareItems($user, $originalItems, $data);
                $totalCents = collect($prepared)->sum('line_total_cents');
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
                $finalFeeCents = $balanceCents > 0 && $data['payment_method'] === Sale::PAYMENT_METHOD_CARD
                    ? Money::percentageOfCents($balanceCents, Sale::CARD_FEE_RATE)
                    : 0;
                $depositFeeCents = $depositCents > 0 ? Money::toCents($deposit->card_fee_amount) : 0;
                $totalFeeCents = $depositFeeCents + $finalFeeCents;
                $summaryMethod = $balanceCents > 0 ? $data['payment_method'] : ($deposit?->payment_method ?? $data['payment_method']);

                $sale = new Sale;
                $sale->appointment_id = $locked->getKey();
                $sale->sold_by = $user->getKey();
                $sale->sold_at = now('UTC');
                $sale->subtotal = Money::fromCents($totalCents);
                $sale->total = Money::fromCents($totalCents);
                $sale->total_services = collect($prepared)->sum('quantity');
                $sale->status = Sale::STATUS_COMPLETED;
                $sale->payment_method = $summaryMethod;
                $sale->card_fee_rate = $summaryMethod === Sale::PAYMENT_METHOD_CARD ? Sale::CARD_FEE_RATE : '0.00';
                $sale->card_fee_amount = Money::fromCents($totalFeeCents);
                $sale->net_amount = Money::fromCents($totalCents - $totalFeeCents);
                $sale->checkout_token = $data['checkout_token'];
                $sale->request_hash = $requestHash;
                $sale->save();
                $sale->sale_number = 'SL-'.str_pad((string) $sale->getKey(), 6, '0', STR_PAD_LEFT);
                $sale->save();

                $allocations = $this->allocateFee($prepared, $totalFeeCents, $totalCents);
                foreach ($prepared as $index => $line) {
                    $item = new SaleItem;
                    $item->sale_id = $sale->getKey();
                    $item->service_id = $line['service_id'];
                    $item->performed_by = $line['performed_by'];
                    $item->appointment_item_id = $line['appointment_item_id'];
                    $item->position = $index + 1;
                    $item->service_name = $line['service_name'];
                    $item->service_description = $line['service_description'];
                    $item->duration_minutes = $line['duration_minutes'];
                    $item->unit_price = Money::fromCents($line['unit_price_cents']);
                    $item->quantity = $line['quantity'];
                    $item->line_total = Money::fromCents($line['line_total_cents']);
                    $item->allocated_card_fee_amount = Money::fromCents($allocations[$index]);
                    $item->net_line_amount = Money::fromCents($line['line_total_cents'] - $allocations[$index]);
                    $item->save();
                }

                if ($depositCents > 0 && $deposit) {
                    $this->createPayment($sale, SalePayment::TYPE_DEPOSIT_APPLIED, $deposit->payment_method, $depositCents, Money::toCents($deposit->card_fee_amount), $deposit->card_fee_rate, $deposit->getKey());
                    $deposit->status = AppointmentDeposit::STATUS_APPLIED;
                    $deposit->applied_amount = Money::fromCents(Money::toCents($deposit->applied_amount) + $depositCents);
                    $deposit->resolved_at = now('UTC');
                    $deposit->resolved_by = $user->getKey();
                    $deposit->resolution_notes = "Aplicado a la venta {$sale->sale_number}.";
                    $deposit->save();
                }
                if ($balanceCents > 0) {
                    $this->createPayment($sale, SalePayment::TYPE_FINAL_PAYMENT, $data['payment_method'], $balanceCents, $finalFeeCents, $data['payment_method'] === Sale::PAYMENT_METHOD_CARD ? Sale::CARD_FEE_RATE : '0.00');
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

                return $sale->load(['soldBy:id,name', 'appointment', 'items.performedBy:id,name', 'payments']);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->findByToken($data['checkout_token']);
            if ($existing) {
                return $this->resolveExisting($existing, $user, $appointment, $requestHash);
            }
            if ($sale = Sale::query()->useWritePdo()->where('appointment_id', $appointment->getKey())->first()) {
                throw ValidationException::withMessages(['appointment' => "Esta cita ya fue convertida en la venta {$sale->sale_number}."]);
            }

            throw $exception;
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
                'service_id' => $appointmentItem?->service_id ?? $service->getKey(),
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

    private function allocateFee(array $lines, int $feeCents, int $totalCents): array
    {
        $allocated = [];
        $used = 0;
        $last = count($lines) - 1;
        foreach ($lines as $index => $line) {
            $value = $index === $last
                ? $feeCents - $used
                : intdiv(($feeCents * $line['line_total_cents']) + intdiv($totalCents, 2), $totalCents);
            $value = min($value, $feeCents - $used);
            $allocated[] = $value;
            $used += $value;
        }

        return $allocated;
    }

    private function createPayment(Sale $sale, string $type, string $method, int $amountCents, int $feeCents, string $rate, ?int $depositId = null): void
    {
        $payment = new SalePayment;
        $payment->sale_id = $sale->getKey();
        $payment->type = $type;
        $payment->method = $method;
        $payment->amount = Money::fromCents($amountCents);
        $payment->card_fee_rate = $rate;
        $payment->card_fee_amount = Money::fromCents($feeCents);
        $payment->net_amount = Money::fromCents($amountCents - $feeCents);
        $payment->appointment_deposit_id = $depositId;
        $payment->save();
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

        return $sale->loadMissing(['soldBy:id,name', 'appointment', 'items.performedBy:id,name', 'payments']);
    }
}

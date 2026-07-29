<?php

namespace App\Actions\Sales;

use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\User;
use App\Support\Money;
use App\Support\Permissions;
use App\Support\SaleAdditionalCharges;
use App\Support\SaleFinancials;
use App\Support\TransferProofStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateSaleAction
{
    private const MAX_AMOUNT_CENTS = 999999999999;

    public function __construct(
        private readonly PersistCompletedSaleAction $persistCompletedSale,
        private readonly TransferProofStorage $proofStorage,
    ) {}

    public function execute(
        User $user,
        array $items,
        string $checkoutToken,
        string $paymentMethod,
        ?UploadedFile $paymentProof = null,
        ?string $clientName = null,
        array $additionalCharges = [],
        ?string $discountAmount = null,
    ): Sale {
        if (! $user->is_active || ! $user->can(Permissions::SALES_ACCESS) || ! $user->can(Permissions::SALES_CREATE)) {
            throw new AuthorizationException;
        }

        if (! in_array($paymentMethod, [Sale::PAYMENT_METHOD_CASH, Sale::PAYMENT_METHOD_CARD, Sale::PAYMENT_METHOD_TRANSFER], true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'El método de pago debe ser efectivo, tarjeta o transferencia.',
            ]);
        }
        if ($paymentProof && $paymentMethod !== Sale::PAYMENT_METHOD_TRANSFER) {
            throw ValidationException::withMessages(['payment_proof' => 'La captura solo puede adjuntarse a una transferencia.']);
        }

        $normalizedItems = $this->normalizeItems($items);
        $clientName = $clientName !== null && trim($clientName) !== '' ? trim($clientName) : null;
        $charges = $this->normalizeCharges($additionalCharges);
        $discountCents = Money::toCents($discountAmount ?? '0.00');
        $this->authorizeDiscount($user, $discountCents);
        $requestHash = $this->requestHash($normalizedItems, $clientName, $charges, $discountCents);

        if ($existing = $this->findByToken($checkoutToken)) {
            return $this->resolveExisting($existing, $user, $requestHash, $paymentMethod);
        }

        $proof = $paymentProof ? $this->storeProof($paymentProof, $user) : null;

        try {
            $sale = DB::transaction(function () use ($user, $normalizedItems, $checkoutToken, $requestHash, $paymentMethod, $proof, $clientName, $charges, $discountCents) {
                if ($existing = $this->findByToken($checkoutToken)) {
                    return $this->resolveExisting($existing, $user, $requestHash, $paymentMethod);
                }

                $serviceIds = array_column($normalizedItems, 'service_id');
                $services = Service::query()
                    ->whereKey($serviceIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $unavailable = [];
                foreach ($serviceIds as $serviceId) {
                    $service = $services->get($serviceId);
                    if (! $service || ! $service->is_active) {
                        $unavailable[] = $service?->name ?? "Servicio #{$serviceId}";
                    }
                }

                if ($unavailable !== []) {
                    throw ValidationException::withMessages([
                        'items' => 'Ya no están disponibles: '.implode(', ', $unavailable).'.',
                    ]);
                }

                $preparedItems = [];
                $subtotalCents = 0;
                $totalServices = 0;

                foreach ($normalizedItems as $item) {
                    $service = $services->get($item['service_id']);
                    $unitPriceCents = Money::toCents($service->price);
                    $lineTotalCents = $unitPriceCents * $item['quantity'];

                    if ($lineTotalCents > self::MAX_AMOUNT_CENTS) {
                        throw ValidationException::withMessages([
                            'items' => "El total de {$service->name} excede el monto permitido.",
                        ]);
                    }

                    $subtotalCents += $lineTotalCents;
                    $totalServices += $item['quantity'];

                    if ($subtotalCents > self::MAX_AMOUNT_CENTS) {
                        throw ValidationException::withMessages([
                            'items' => 'El total de la venta excede el monto permitido.',
                        ]);
                    }

                    $preparedItems[] = [
                        'service_id' => $service->getKey(),
                        'appointment_item_id' => null,
                        'performed_by' => $user->getKey(),
                        'service_name' => $service->name,
                        'service_description' => $service->description,
                        'duration_minutes' => $service->duration_minutes,
                        'quantity' => $item['quantity'],
                        'unit_price_cents' => $unitPriceCents,
                        'line_total_cents' => $lineTotalCents,
                    ];
                }

                $totalCents = $subtotalCents + array_sum(array_column($charges, 'amount_cents')) - $discountCents;
                if ($totalCents < 0) {
                    throw ValidationException::withMessages(['discount_amount' => 'El descuento no puede superar el subtotal.']);
                }
                $payment = SaleFinancials::payment(SalePayment::TYPE_FINAL_PAYMENT, $paymentMethod, $totalCents);

                return $this->persistCompletedSale->execute(
                    $user,
                    $preparedItems,
                    [[...$payment, ...($proof ?? [])]],
                    $checkoutToken,
                    $requestHash,
                    null,
                    $clientName,
                    $charges,
                    $discountCents,
                );
            }, 3);

            if ($proof && ! $sale->payments->contains('proof_path', $proof['proof_path'])) {
                $this->proofStorage->delete($proof);
            }

            return $sale;
        } catch (UniqueConstraintViolationException $exception) {
            if (! $this->isCheckoutTokenConflict($exception)) {
                $this->proofStorage->delete($proof);
                throw $exception;
            }

            $existing = $this->findByToken($checkoutToken);
            if (! $existing) {
                $this->proofStorage->delete($proof);
                throw $exception;
            }

            $sale = $this->resolveExisting($existing, $user, $requestHash, $paymentMethod);
            $this->proofStorage->delete($proof);

            return $sale;
        } catch (Throwable $exception) {
            $this->proofStorage->delete($proof);

            throw $exception;
        }
    }

    private function storeProof(UploadedFile $file, User $user): array
    {
        try {
            return $this->proofStorage->store($file, $user);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'payment_proof' => 'No se pudo guardar la captura. Inténtalo nuevamente sin perder el carrito.',
            ]);
        }
    }

    private function normalizeItems(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Agrega al menos un servicio.']);
        }

        $quantities = [];
        foreach ($items as $item) {
            $serviceId = (int) ($item['service_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            $quantities[$serviceId] = ($quantities[$serviceId] ?? 0) + $quantity;

            if ($serviceId < 1 || $quantity < 1 || $quantities[$serviceId] > 50) {
                throw ValidationException::withMessages([
                    'items' => 'Cada servicio debe tener una cantidad entre 1 y 50.',
                ]);
            }
        }

        return array_map(
            fn (int $serviceId, int $quantity) => ['service_id' => $serviceId, 'quantity' => $quantity],
            array_keys($quantities),
            array_values($quantities),
        );
    }

    private function requestHash(array $items, ?string $clientName, array $charges = [], int $discountCents = 0): string
    {
        usort($items, fn (array $left, array $right) => $left['service_id'] <=> $right['service_id']);

        $payload = ['items' => $items, 'client_name' => $clientName, 'additional_charges' => $charges, 'discount_cents' => $discountCents];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
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

    private function findByToken(string $checkoutToken): ?Sale
    {
        return Sale::query()
            ->useWritePdo()
            ->where('checkout_token', $checkoutToken)
            ->first();
    }

    private function resolveExisting(Sale $sale, User $user, string $requestHash, string $paymentMethod): Sale
    {
        if ($sale->sold_by !== $user->getKey()) {
            throw new AuthorizationException;
        }

        if (! hash_equals($sale->request_hash, $requestHash)) {
            throw ValidationException::withMessages([
                'checkout_token' => 'Esta confirmación ya fue utilizada para otra selección. Inicia una nueva venta.',
            ]);
        }

        if ($sale->payment_method !== $paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method' => 'Esta confirmación ya fue utilizada con otro método de pago. Inicia una nueva venta.',
            ]);
        }

        return $sale->loadMissing(['soldBy:id,name', 'items.performedBy:id,name', 'payments']);
    }

    private function isCheckoutTokenConflict(UniqueConstraintViolationException $exception): bool
    {
        $driver = DB::connection($exception->getConnectionName())->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => $exception->index === 'sales_checkout_token_unique',
            'sqlite' => $exception->columns === ['checkout_token'],
            default => false,
        };
    }
}

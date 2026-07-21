<?php

namespace App\Actions\Sales;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class CreateSaleAction
{
    private const MAX_AMOUNT_CENTS = 999999999999;

    public function execute(User $user, array $items, string $checkoutToken, string $paymentMethod): Sale
    {
        if (! $user->is_active || ! $user->can(Permissions::SALES_ACCESS) || ! $user->can(Permissions::SALES_CREATE)) {
            throw new AuthorizationException;
        }

        if (! in_array($paymentMethod, [Sale::PAYMENT_METHOD_CASH, Sale::PAYMENT_METHOD_CARD], true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'El método de pago debe ser efectivo o tarjeta.',
            ]);
        }

        $normalizedItems = $this->normalizeItems($items);
        $requestHash = $this->requestHash($normalizedItems);

        if ($existing = $this->findByToken($checkoutToken)) {
            return $this->resolveExisting($existing, $user, $requestHash, $paymentMethod);
        }

        try {
            return DB::transaction(function () use ($user, $normalizedItems, $checkoutToken, $requestHash, $paymentMethod) {
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
                    $unitPriceCents = $this->decimalToCents($service->price);
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
                        'service' => $service,
                        'quantity' => $item['quantity'],
                        'unit_price' => $this->centsToDecimal($unitPriceCents),
                        'line_total' => $this->centsToDecimal($lineTotalCents),
                    ];
                }

                $cardFeeCents = $paymentMethod === Sale::PAYMENT_METHOD_CARD
                    ? $this->percentageOfCents($subtotalCents, Sale::CARD_FEE_RATE)
                    : 0;
                $netAmountCents = $subtotalCents - $cardFeeCents;

                $sale = new Sale;
                $sale->sold_by = $user->getKey();
                $sale->sold_at = now('UTC');
                $sale->subtotal = $this->centsToDecimal($subtotalCents);
                $sale->total = $this->centsToDecimal($subtotalCents);
                $sale->total_services = $totalServices;
                $sale->status = Sale::STATUS_COMPLETED;
                $sale->payment_method = $paymentMethod;
                $sale->card_fee_rate = $paymentMethod === Sale::PAYMENT_METHOD_CARD ? Sale::CARD_FEE_RATE : '0.00';
                $sale->card_fee_amount = $this->centsToDecimal($cardFeeCents);
                $sale->net_amount = $this->centsToDecimal($netAmountCents);
                $sale->checkout_token = $checkoutToken;
                $sale->request_hash = $requestHash;
                $sale->save();

                $sale->sale_number = 'SL-'.str_pad((string) $sale->getKey(), 6, '0', STR_PAD_LEFT);
                $sale->save();

                foreach ($preparedItems as $preparedItem) {
                    $service = $preparedItem['service'];
                    $saleItem = new SaleItem;
                    $saleItem->sale_id = $sale->getKey();
                    $saleItem->service_id = $service->getKey();
                    $saleItem->service_name = $service->name;
                    $saleItem->service_description = $service->description;
                    $saleItem->duration_minutes = $service->duration_minutes;
                    $saleItem->unit_price = $preparedItem['unit_price'];
                    $saleItem->quantity = $preparedItem['quantity'];
                    $saleItem->line_total = $preparedItem['line_total'];
                    $saleItem->save();
                }

                return $sale->load(['soldBy:id,name', 'items']);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if (! $this->isCheckoutTokenConflict($exception)) {
                throw $exception;
            }

            $existing = $this->findByToken($checkoutToken);
            if (! $existing) {
                throw $exception;
            }

            return $this->resolveExisting($existing, $user, $requestHash, $paymentMethod);
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

    private function requestHash(array $items): string
    {
        usort($items, fn (array $left, array $right) => $left['service_id'] <=> $right['service_id']);

        return hash('sha256', json_encode($items, JSON_THROW_ON_ERROR));
    }

    private function decimalToCents(string $amount): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new LogicException('El precio almacenado no tiene un formato decimal válido.');
        }

        $whole = (int) $matches[1];
        $fraction = (int) str_pad($matches[2] ?? '', 2, '0');

        return ($whole * 100) + $fraction;
    }

    private function centsToDecimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function percentageOfCents(int $amountCents, string $percentage): int
    {
        $percentageHundredths = $this->decimalToCents($percentage);

        return intdiv(($amountCents * $percentageHundredths) + 5000, 10000);
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

        return $sale->loadMissing(['soldBy:id,name', 'items']);
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

<?php

namespace App\Support;

use App\Models\Sale;
use Illuminate\Validation\ValidationException;

final class SaleFinancials
{
    public static function payment(
        string $type,
        string $method,
        int $amountCents,
        ?int $feeCents = null,
        ?string $feeRate = null,
        ?int $appointmentDepositId = null,
    ): array {
        if (! in_array($method, [Sale::PAYMENT_METHOD_CASH, Sale::PAYMENT_METHOD_CARD, Sale::PAYMENT_METHOD_TRANSFER], true) || $amountCents < 0) {
            throw ValidationException::withMessages(['payment_method' => 'El pago no tiene un método o monto válido.']);
        }

        $rate = $method === Sale::PAYMENT_METHOD_CARD
            ? ($feeRate ?? Sale::CARD_FEE_RATE)
            : '0.00';
        $fee = $method === Sale::PAYMENT_METHOD_CARD
            ? ($feeCents ?? Money::percentageOfCents($amountCents, $rate))
            : 0;

        if ($fee < 0 || $fee > $amountCents) {
            throw ValidationException::withMessages(['payment_method' => 'La comisión del pago no es válida.']);
        }

        return [
            'type' => $type,
            'method' => $method,
            'amount_cents' => $amountCents,
            'fee_rate' => $rate,
            'fee_cents' => $fee,
            'net_cents' => $amountCents - $fee,
            'appointment_deposit_id' => $appointmentDepositId,
        ];
    }

    public static function summarize(array $lines, array $payments, array $additionalCharges = [], int $discountCents = 0): array
    {
        if ($lines === [] || $payments === []) {
            throw ValidationException::withMessages(['items' => 'La venta requiere servicios y pagos.']);
        }

        $totalCents = 0;
        $totalServices = 0;
        foreach ($lines as $line) {
            $quantity = (int) ($line['quantity'] ?? 0);
            $unitPriceCents = (int) ($line['unit_price_cents'] ?? -1);
            $lineTotalCents = (int) ($line['line_total_cents'] ?? -1);
            if ($quantity < 1 || $unitPriceCents < 0 || $lineTotalCents !== $unitPriceCents * $quantity) {
                throw ValidationException::withMessages(['items' => 'Una línea de venta no tiene cantidades o totales válidos.']);
            }
            $totalCents += $lineTotalCents;
            $totalServices += $quantity;
        }

        $additionalCents = array_sum(array_column($additionalCharges, 'amount_cents'));
        $subtotalCents = $totalCents + $additionalCents;
        if ($discountCents < 0 || $discountCents > $subtotalCents) {
            throw ValidationException::withMessages(['discount_amount' => 'El descuento no puede superar el subtotal.']);
        }
        $finalTotalCents = $subtotalCents - $discountCents;
        $paidCents = array_sum(array_column($payments, 'amount_cents'));
        $feeCents = array_sum(array_column($payments, 'fee_cents'));
        if ($paidCents !== $finalTotalCents || $feeCents > $finalTotalCents) {
            throw ValidationException::withMessages(['items' => 'Los pagos no coinciden con el total de la venta.']);
        }

        return [
            'services_cents' => $totalCents,
            'additional_cents' => $additionalCents,
            'subtotal_cents' => $subtotalCents,
            'discount_cents' => $discountCents,
            'total_cents' => $finalTotalCents,
            'total_services' => $totalServices,
            'fee_cents' => $feeCents,
            'net_cents' => $finalTotalCents - $feeCents,
            ...self::allocateRevenue(
                array_column($lines, 'line_total_cents'),
                array_column($additionalCharges, 'amount_cents'),
                $discountCents,
                $feeCents,
            ),
        ];
    }

    /** Distributes reductions and POS fees across the final financial components. */
    public static function allocateRevenue(array $lineAmounts, array $additionalAmounts, int $discountCents, int $feeCents): array
    {
        $amounts = [...$lineAmounts, ...$additionalAmounts];
        $subtotalCents = array_sum($amounts);
        if ($subtotalCents === 0) {
            return [
                'line_final_cents' => array_fill(0, count($lineAmounts), 0),
                'additional_final_cents' => array_fill(0, count($additionalAmounts), 0),
                'fee_allocations' => array_fill(0, count($lineAmounts), 0),
                'additional_fee_allocations' => array_fill(0, count($additionalAmounts), 0),
            ];
        }

        $discountAllocations = self::allocateProportionally($amounts, $discountCents, $subtotalCents);
        $finalAmounts = array_map(fn (int $amount, int $discount) => $amount - $discount, $amounts, $discountAllocations);
        $feeAllocations = self::allocateProportionally($finalAmounts, $feeCents, array_sum($finalAmounts));

        return [
            'line_final_cents' => array_slice($finalAmounts, 0, count($lineAmounts)),
            'additional_final_cents' => array_slice($finalAmounts, count($lineAmounts)),
            'fee_allocations' => array_slice($feeAllocations, 0, count($lineAmounts)),
            'additional_fee_allocations' => array_slice($feeAllocations, count($lineAmounts)),
        ];
    }

    private static function allocateProportionally(array $amounts, int $allocationCents, int $denominatorCents): array
    {
        if ($amounts === [] || $allocationCents === 0 || $denominatorCents === 0) {
            return array_fill(0, count($amounts), 0);
        }

        $allocations = [];
        $used = 0;
        $last = count($amounts) - 1;
        foreach ($amounts as $index => $amount) {
            $allocation = $index === $last
                ? $allocationCents - $used
                : intdiv(($allocationCents * $amount) + intdiv($denominatorCents, 2), $denominatorCents);
            $allocation = min($allocation, $allocationCents - $used, $amount);
            $allocations[] = $allocation;
            $used += $allocation;
        }

        return $allocations;
    }

    public static function distributeCents(array $weights, int $totalCents): array
    {
        $weightTotal = array_sum($weights);
        if ($weights === [] || $totalCents === 0 || $weightTotal === 0) {
            return array_fill(0, count($weights), 0);
        }

        $allocations = array_map(fn (int $weight) => intdiv($totalCents * $weight, $weightTotal), $weights);
        $remaining = $totalCents - array_sum($allocations);
        $remainders = array_map(fn (int $weight) => ($totalCents * $weight) % $weightTotal, $weights);
        arsort($remainders, SORT_NUMERIC);
        foreach (array_keys($remainders) as $index) {
            if ($remaining-- === 0) {
                break;
            }
            $allocations[$index]++;
        }

        return array_values($allocations);
    }
}

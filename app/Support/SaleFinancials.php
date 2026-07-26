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

    public static function summarize(array $lines, array $payments): array
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

        $paidCents = array_sum(array_column($payments, 'amount_cents'));
        $feeCents = array_sum(array_column($payments, 'fee_cents'));
        if ($paidCents !== $totalCents || $feeCents > $totalCents) {
            throw ValidationException::withMessages(['items' => 'Los pagos no coinciden con el total de la venta.']);
        }

        return [
            'total_cents' => $totalCents,
            'total_services' => $totalServices,
            'fee_cents' => $feeCents,
            'net_cents' => $totalCents - $feeCents,
            'fee_allocations' => self::allocateFee($lines, $feeCents, $totalCents),
        ];
    }

    private static function allocateFee(array $lines, int $feeCents, int $totalCents): array
    {
        if ($totalCents === 0) {
            return array_fill(0, count($lines), 0);
        }

        $allocations = [];
        $used = 0;
        $last = count($lines) - 1;
        foreach ($lines as $index => $line) {
            $allocation = $index === $last
                ? $feeCents - $used
                : intdiv(($feeCents * $line['line_total_cents']) + intdiv($totalCents, 2), $totalCents);
            $allocation = min($allocation, $feeCents - $used);
            $allocations[] = $allocation;
            $used += $allocation;
        }

        return $allocations;
    }
}

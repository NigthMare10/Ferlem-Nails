<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class SaleAdditionalCharges
{
    public static function canonicalizeInput(array $charges): array
    {
        return array_map(function ($charge): array {
            if (! is_array($charge)) {
                return [];
            }

            return [
                'name' => $charge['name'] ?? $charge['description'] ?? null,
                'amount' => $charge['amount'] ?? null,
                'performed_by' => isset($charge['performed_by']) ? (int) $charge['performed_by'] : null,
            ];
        }, array_values($charges));
    }

    public static function normalize(array $charges): array
    {
        return array_map(function (array $charge): array {
            $name = trim((string) ($charge['name'] ?? $charge['description'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages([
                    'additional_charges' => 'Cada cargo adicional debe incluir un nombre.',
                ]);
            }

            try {
                $amountCents = Money::toCents((string) ($charge['amount'] ?? ''));
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages([
                    'additional_charges' => 'Cada cargo adicional debe incluir un monto válido.',
                ]);
            }

            if ($amountCents < 1) {
                throw ValidationException::withMessages([
                    'additional_charges' => 'Cada cargo adicional debe ser mayor que cero.',
                ]);
            }

            return ['name' => $name, 'amount_cents' => $amountCents, 'performed_by' => $charge['performed_by'] ?? null];
        }, self::canonicalizeInput($charges));
    }
}

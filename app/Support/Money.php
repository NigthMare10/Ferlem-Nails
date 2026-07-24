<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function toCents(string $amount): int
    {
        if (! preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('El monto no tiene un formato decimal válido.');
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    public static function fromCents(int $cents): string
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('El monto no puede ser negativo.');
        }

        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function percentageOfCents(int $amountCents, string $percentage): int
    {
        $percentageHundredths = self::toCents($percentage);

        return intdiv(($amountCents * $percentageHundredths) + 5000, 10000);
    }
}

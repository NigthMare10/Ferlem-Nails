<?php

namespace App\Modules\Shared\Support;

class Money
{
    public static function format(int $amount, string $symbol = 'L'): string
    {
        return $symbol.' '.number_format($amount / 100, 2, '.', ',');
    }
}

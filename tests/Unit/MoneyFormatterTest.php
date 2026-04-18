<?php

namespace Tests\Unit;

use App\Modules\Shared\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyFormatterTest extends TestCase
{
    public function test_formatea_montos_en_lempiras_de_forma_consistente(): void
    {
        $this->assertSame('L 1,234.56', Money::format(123456));
        $this->assertSame('L 0.00', Money::format(0));
    }
}

<?php

namespace Tests\Unit\Support;

use App\Support\MoneyMath;
use PHPUnit\Framework\TestCase;

class MoneyMathTest extends TestCase
{
    public function test_round_half_even(): void
    {
        $this->assertSame(2, MoneyMath::roundHalfEvenToInt('2.5'));
        $this->assertSame(4, MoneyMath::roundHalfEvenToInt('3.5'));
        $this->assertSame(2, MoneyMath::roundHalfEvenToInt('2.4'));
        $this->assertSame(3, MoneyMath::roundHalfEvenToInt('2.6'));
        $this->assertSame(-2, MoneyMath::roundHalfEvenToInt('-2.5'));
    }

    public function test_convert_uses_bcmath_not_float(): void
    {
        $this->assertSame(8500, MoneyMath::convert(1_000_000, '0.0085'));
    }

    public function test_percent_of(): void
    {
        $this->assertSame(500_000, MoneyMath::percentOf(100_000_000, '0.5'));
    }
}

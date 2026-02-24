<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\Support\MoneyFormatter;
use PHPUnit\Framework\TestCase;

/**
 * MoneyFormatter birim testleri.
 *
 * @internal
 *
 * @coversNothing
 */
class MoneyFormatterTest extends TestCase
{
    public function test_to_penny(): void
    {
        $this->assertSame(15000, MoneyFormatter::toPenny(150.00));
        $this->assertSame(9999, MoneyFormatter::toPenny(99.99));
        $this->assertSame(100, MoneyFormatter::toPenny(1.00));
        $this->assertSame(1, MoneyFormatter::toPenny(0.01));
    }

    public function test_to_decimal_string(): void
    {
        $this->assertSame('150.00', MoneyFormatter::toDecimalString(150.00));
        $this->assertSame('99.99', MoneyFormatter::toDecimalString(99.99));
        $this->assertSame('1.00', MoneyFormatter::toDecimalString(1.0));
        $this->assertSame('0.01', MoneyFormatter::toDecimalString(0.01));
    }

    public function test_to_float_from_int(): void
    {
        $this->assertSame(150.0, MoneyFormatter::toFloat(15000));
        $this->assertSame(99.99, MoneyFormatter::toFloat(9999));
        $this->assertSame(1.0, MoneyFormatter::toFloat(100));
    }

    public function test_to_float_from_string(): void
    {
        $this->assertSame(150.0, MoneyFormatter::toFloat('150.00'));
        $this->assertSame(99.99, MoneyFormatter::toFloat('99.99'));
    }

    public function test_to_decimal(): void
    {
        $this->assertSame('150.00', MoneyFormatter::toDecimal(15000));
        $this->assertSame('99.99', MoneyFormatter::toDecimal(9999));
    }
}

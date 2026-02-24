<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\Enums\CardType;
use Arpay\Enums\Currency;
use Arpay\Enums\Gateway;
use Arpay\Enums\PaymentStatus;
use Arpay\Enums\TransactionType;
use PHPUnit\Framework\TestCase;

/**
 * Enum sınıfları birim testleri.
 *
 * @internal
 *
 * @coversNothing
 */
class EnumTest extends TestCase
{
    public function test_gateway_from_short_name(): void
    {
        $this->assertSame(Gateway::PayTR, Gateway::fromShortName('paytr'));
        $this->assertSame(Gateway::Iyzico, Gateway::fromShortName('iyzico'));
        $this->assertSame(Gateway::Papara, Gateway::fromShortName('papara'));
    }

    public function test_gateway_display_name(): void
    {
        $this->assertSame('PayTR', Gateway::PayTR->displayName());
        $this->assertSame('Iyzico', Gateway::Iyzico->displayName());
        $this->assertSame('Papara', Gateway::Papara->displayName());
    }

    public function test_all_gateway_cases(): void
    {
        $cases = Gateway::cases();
        $this->assertCount(9, $cases);
    }

    public function test_currency_values(): void
    {
        $this->assertSame('TRY', Currency::TRY->value);
        $this->assertSame('USD', Currency::USD->value);
        $this->assertSame('EUR', Currency::EUR->value);
        $this->assertSame('GBP', Currency::GBP->value);
    }

    public function test_payment_status_values(): void
    {
        $this->assertSame('successful', PaymentStatus::Successful->value);
        $this->assertSame('failed', PaymentStatus::Failed->value);
        $this->assertSame('pending', PaymentStatus::Pending->value);
    }

    public function test_card_type_detect_visa(): void
    {
        $this->assertSame(CardType::Visa, CardType::detectFromBin('4111111111111111'));
    }

    public function test_card_type_detect_mastercard(): void
    {
        $this->assertSame(CardType::MasterCard, CardType::detectFromBin('5528790000000008'));
    }

    public function test_card_type_detect_amex(): void
    {
        $this->assertSame(CardType::Amex, CardType::detectFromBin('371449635398431'));
    }

    public function test_transaction_type_values(): void
    {
        $this->assertSame('single', TransactionType::Single->value);
        $this->assertSame('installment', TransactionType::Installment->value);
        $this->assertSame('refund', TransactionType::Refund->value);
        $this->assertSame('secure3d', TransactionType::Secure3D->value);
    }
}

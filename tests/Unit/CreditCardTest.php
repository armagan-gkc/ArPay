<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\DTO\CreditCard;
use Arpay\Enums\CardType;
use PHPUnit\Framework\TestCase;

/**
 * CreditCard DTO birim testleri.
 *
 * @internal
 *
 * @coversNothing
 */
class CreditCardTest extends TestCase
{
    public function test_create_card_with_valid_data(): void
    {
        $card = CreditCard::create(
            cardHolderName: 'Armağan Gökce',
            cardNumber: '5528790000000008',
            expireMonth: '12',
            expireYear: '2030',
            cvv: '123',
        );

        $this->assertSame('Armağan Gökce', $card->cardHolderName);
        $this->assertSame('5528790000000008', $card->cardNumber);
        $this->assertSame('12', $card->expireMonth);
        $this->assertSame('2030', $card->expireYear);
        $this->assertSame('123', $card->cvv);
    }

    public function test_create_strips_spaces_and_dashes(): void
    {
        $card = CreditCard::create(
            cardHolderName: 'Test User',
            cardNumber: '5528-7900-0000-0008',
            expireMonth: '1',
            expireYear: '30',
            cvv: '456',
        );

        $this->assertSame('5528790000000008', $card->cardNumber);
        $this->assertSame('01', $card->expireMonth);
        $this->assertSame('2030', $card->expireYear);
    }

    public function test_luhn_check_valid(): void
    {
        // MasterCard test kartı
        $this->assertTrue(CreditCard::luhnCheck('5528790000000008'));
    }

    public function test_luhn_check_invalid(): void
    {
        $this->assertFalse(CreditCard::luhnCheck('1234567890123456'));
    }

    public function test_luhn_check_short_number(): void
    {
        $this->assertFalse(CreditCard::luhnCheck('123'));
    }

    public function test_get_bin(): void
    {
        $card = CreditCard::create(
            cardHolderName: 'Test',
            cardNumber: '5528790000000008',
            expireMonth: '12',
            expireYear: '2030',
            cvv: '123',
        );

        $this->assertSame('552879', $card->getBin());
    }

    public function test_get_masked_number(): void
    {
        $card = CreditCard::create(
            cardHolderName: 'Test',
            cardNumber: '5528790000000008',
            expireMonth: '12',
            expireYear: '2030',
            cvv: '123',
        );

        $masked = $card->getMaskedNumber();
        $this->assertSame('552879******0008', $masked);
    }

    public function test_detect_mastercard(): void
    {
        $card = CreditCard::create(
            cardHolderName: 'Test',
            cardNumber: '5528790000000008',
            expireMonth: '12',
            expireYear: '2030',
            cvv: '123',
        );

        $this->assertSame(CardType::MasterCard, $card->getCardType());
    }

    public function test_detect_visa(): void
    {
        $card = CreditCard::create(
            cardHolderName: 'Test',
            cardNumber: '4155650100416111',
            expireMonth: '12',
            expireYear: '2030',
            cvv: '123',
        );

        $this->assertSame(CardType::Visa, $card->getCardType());
    }

    public function test_is_valid_returns_true_for_valid_card(): void
    {
        $card = CreditCard::create(
            cardHolderName: 'Test',
            cardNumber: '5528790000000008',
            expireMonth: '12',
            expireYear: '2030',
            cvv: '123',
        );

        $this->assertTrue($card->isValid());
    }
}

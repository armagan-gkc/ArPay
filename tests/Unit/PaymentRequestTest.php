<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\DTO\PaymentRequest;
use Arpay\DTO\CreditCard;
use Arpay\DTO\Customer;
use Arpay\DTO\CartItem;
use PHPUnit\Framework\TestCase;

/**
 * PaymentRequest Builder birim testleri.
 */
class PaymentRequestTest extends TestCase
{
    public function test_build_basic_request(): void
    {
        $request = PaymentRequest::create()
            ->amount(150.00)
            ->currency('TRY')
            ->orderId('ORDER-001')
            ->description('Test ödemesi');

        $this->assertSame(150.00, $request->getAmount());
        $this->assertSame('TRY', $request->getCurrency());
        $this->assertSame('ORDER-001', $request->getOrderId());
        $this->assertSame('Test ödemesi', $request->getDescription());
    }

    public function test_build_request_with_card(): void
    {
        $card = CreditCard::create(
            cardHolderName: 'Test User',
            cardNumber: '5528790000000008',
            expireMonth: '12',
            expireYear: '2030',
            cvv: '123',
        );

        $request = PaymentRequest::create()
            ->amount(100.00)
            ->card($card);

        $this->assertNotNull($request->getCard());
        $this->assertSame('5528790000000008', $request->getCard()->cardNumber);
    }

    public function test_build_request_with_customer(): void
    {
        $customer = Customer::create(
            firstName: 'Armağan',
            lastName: 'Gökce',
            email: 'test@example.com',
            phone: '5551234567',
            ip: '127.0.0.1',
        );

        $request = PaymentRequest::create()
            ->amount(200.00)
            ->customer($customer);

        $this->assertNotNull($request->getCustomer());
        $this->assertSame('Armağan', $request->getCustomer()->firstName);
    }

    public function test_build_request_with_installment(): void
    {
        $request = PaymentRequest::create()
            ->amount(600.00)
            ->installmentCount(3);

        $this->assertSame(3, $request->getInstallmentCount());
    }

    public function test_build_request_with_cart_items(): void
    {
        $request = PaymentRequest::create()
            ->amount(250.00)
            ->addCartItem(CartItem::create(
                id: 'ITEM-001',
                name: 'Ürün 1',
                category: 'Yazılım',
                price: 150.00,
                quantity: 1,
            ))
            ->addCartItem(CartItem::create(
                id: 'ITEM-002',
                name: 'Ürün 2',
                category: 'Kitap',
                price: 100.00,
                quantity: 1,
            ));

        $this->assertCount(2, $request->getCartItems());
        $this->assertSame('ITEM-001', $request->getCartItems()[0]->id);
    }

    public function test_default_installment_is_one(): void
    {
        $request = PaymentRequest::create()->amount(100.00);

        $this->assertSame(1, $request->getInstallmentCount());
    }

    public function test_default_currency_is_try(): void
    {
        $request = PaymentRequest::create()->amount(100.00);

        $this->assertSame('TRY', $request->getCurrency());
    }

    public function test_meta_data(): void
    {
        $request = PaymentRequest::create()
            ->amount(100.00)
            ->meta('custom_field', 'custom_value');

        $this->assertSame('custom_value', $request->getMeta('custom_field'));
        $this->assertNull($request->getMeta('non_existent'));
    }
}

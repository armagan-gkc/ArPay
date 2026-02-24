<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\DTO\CartItem;
use Arpay\DTO\CreditCard;
use Arpay\DTO\Customer;
use Arpay\DTO\PaymentRequest;
use Arpay\DTO\RefundRequest;
use Arpay\DTO\QueryRequest;
use Arpay\Gateways\PayTR\PayTRGateway;
use Arpay\Support\Config;
use Arpay\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * PayTR Gateway birim testleri (sahte HTTP istemci ile).
 */
class PayTRGatewayTest extends TestCase
{
    private PayTRGateway $gateway;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->gateway = new PayTRGateway();
        $this->httpClient = new MockHttpClient();

        $this->gateway->configure(new Config([
            'merchant_id'   => 'TEST_MID',
            'merchant_key'  => 'TEST_KEY',
            'merchant_salt' => 'TEST_SALT',
            'test_mode'     => true,
        ]));
        $this->gateway->setHttpClient($this->httpClient);
    }

    public function test_gateway_name(): void
    {
        $this->assertSame('PayTR', $this->gateway->getName());
        $this->assertSame('paytr', $this->gateway->getShortName());
    }

    public function test_supported_features(): void
    {
        $features = $this->gateway->getSupportedFeatures();
        $this->assertContains('pay', $features);
        $this->assertContains('refund', $features);
        $this->assertContains('3dsecure', $features);
    }

    public function test_successful_payment(): void
    {
        $this->httpClient->addResponse(200, [
            'status'  => 'success',
            'trans_id' => 'TXN-12345',
        ]);

        $request = PaymentRequest::create()
            ->amount(150.00)
            ->currency('TRY')
            ->orderId('ORDER-001')
            ->description('Test ödemesi')
            ->card(CreditCard::create(
                cardHolderName: 'Test User',
                cardNumber: '5528790000000008',
                expireMonth: '12',
                expireYear: '2030',
                cvv: '123',
            ))
            ->customer(Customer::create(
                firstName: 'Test',
                lastName: 'User',
                email: 'test@example.com',
                phone: '5551234567',
                ip: '127.0.0.1',
            ))
            ->addCartItem(CartItem::create(
                id: 'ITEM-1',
                name: 'Test Ürün',
                category: 'Yazılım',
                price: 150.00,
                quantity: 1,
            ));

        $response = $this->gateway->pay($request);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('TXN-12345', $response->getTransactionId());
    }

    public function test_failed_payment(): void
    {
        $this->httpClient->addResponse(200, [
            'status'  => 'failed',
            'err_no'  => 'DECLINED',
            'err_msg' => 'Kart reddedildi.',
        ]);

        $request = PaymentRequest::create()
            ->amount(150.00)
            ->orderId('ORDER-002')
            ->card(CreditCard::create(
                cardHolderName: 'Test User',
                cardNumber: '5528790000000008',
                expireMonth: '12',
                expireYear: '2030',
                cvv: '123',
            ));

        $response = $this->gateway->pay($request);

        $this->assertFalse($response->isSuccessful());
    }

    public function test_payment_without_card_fails(): void
    {
        $request = PaymentRequest::create()
            ->amount(100.00)
            ->orderId('ORDER-003');

        $response = $this->gateway->pay($request);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('CARD_MISSING', $response->getErrorCode());
    }

    public function test_successful_refund(): void
    {
        $this->httpClient->addResponse(200, [
            'status'  => 'success',
            'trans_id' => 'REFUND-001',
        ]);

        $request = RefundRequest::create()
            ->transactionId('TXN-12345')
            ->orderId('ORDER-001')
            ->amount(50.00)
            ->reason('Müşteri iade talebi');

        $response = $this->gateway->refund($request);

        $this->assertTrue($response->isSuccessful());
    }

    public function test_successful_query(): void
    {
        $this->httpClient->addResponse(200, [
            'status'      => 'success',
            'trans_id'    => 'TXN-12345',
            'order_id'    => 'ORDER-001',
            'amount'      => 15000,
            'trans_status' => 'approved',
        ]);

        $request = QueryRequest::create()
            ->transactionId('TXN-12345')
            ->orderId('ORDER-001');

        $response = $this->gateway->query($request);

        $this->assertTrue($response->isSuccessful());
    }

    public function test_request_sent_to_test_url(): void
    {
        $this->httpClient->addResponse(200, ['status' => 'success', 'trans_id' => 'T1']);

        $this->gateway->pay(
            PaymentRequest::create()
                ->amount(100.00)
                ->orderId('ORDER-URL-TEST')
                ->card(CreditCard::create(
                    cardHolderName: 'Test',
                    cardNumber: '5528790000000008',
                    expireMonth: '12',
                    expireYear: '2030',
                    cvv: '123',
                ))
        );

        $lastRequest = $this->httpClient->getLastRequest();
        $this->assertNotNull($lastRequest);
        /* Test modunda sandbox URL kullanılmalı */
        $this->assertStringContainsString('test.paytr.com', $lastRequest['url']);
    }
}

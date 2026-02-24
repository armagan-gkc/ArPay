<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\DTO\CreditCard;
use Arpay\DTO\Customer;
use Arpay\DTO\PaymentRequest;
use Arpay\DTO\QueryRequest;
use Arpay\DTO\RefundRequest;
use Arpay\Gateways\PayU\PayUGateway;
use Arpay\Support\Config;
use Arpay\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * PayU Gateway birim testleri.
 *
 * @internal
 *
 * @coversNothing
 */
class PayUGatewayTest extends TestCase
{
    private PayUGateway $gateway;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->gateway = new PayUGateway();
        $this->httpClient = new MockHttpClient();

        $this->gateway->configure(new Config([
            'merchant' => 'TEST_MERCHANT',
            'secret_key' => 'test_secret_key',
            'test_mode' => true,
        ]));
        $this->gateway->setHttpClient($this->httpClient);
    }

    public function test_gateway_name(): void
    {
        $this->assertSame('PayU', $this->gateway->getName());
        $this->assertSame('payu', $this->gateway->getShortName());
    }

    public function test_supported_features(): void
    {
        $features = $this->gateway->getSupportedFeatures();
        $this->assertContains('pay', $features);
        $this->assertContains('refund', $features);
        $this->assertContains('query', $features);
        $this->assertContains('subscription', $features);
    }

    public function test_successful_payment(): void
    {
        $this->httpClient->addResponse(200, [
            'STATUS' => 'SUCCESS',
            'RETURN_CODE' => 'AUTHORIZED',
            'REFNO' => 'PU-TXN-001',
            'ORDER_REF' => 'ORDER-PU-001',
        ]);

        $request = PaymentRequest::create()
            ->amount(500.00)
            ->currency('TRY')
            ->orderId('ORDER-PU-001')
            ->card(CreditCard::create('Test User', '5528790000000008', '12', '2030', '123'))
            ->customer(Customer::create('Test', 'User', 'test@example.com', '5551234567', '127.0.0.1'));

        $response = $this->gateway->pay($request);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('PU-TXN-001', $response->getTransactionId());
    }

    public function test_failed_payment(): void
    {
        $this->httpClient->addResponse(200, [
            'STATUS' => 'FAILED',
            'RETURN_CODE' => 'DECLINED',
            'RETURN_MESSAGE' => 'Kart reddedildi.',
        ]);

        $request = PaymentRequest::create()
            ->amount(500.00)
            ->orderId('ORDER-PU-002')
            ->card(CreditCard::create('Test User', '5528790000000008', '12', '2030', '123'));

        $response = $this->gateway->pay($request);

        $this->assertFalse($response->isSuccessful());
    }

    public function test_payment_without_card_fails(): void
    {
        $request = PaymentRequest::create()
            ->amount(100.00)
            ->orderId('ORDER-PU-003');

        $response = $this->gateway->pay($request);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('CARD_MISSING', $response->getErrorCode());
    }

    public function test_successful_refund(): void
    {
        $this->httpClient->addResponse(200, [
            'RESPONSE_CODE' => '0',
            'STATUS' => 'SUCCESS',
            'IRN_REFNO' => 'PU-REF-001',
        ]);

        $request = RefundRequest::create()
            ->transactionId('PU-TXN-001')
            ->amount(200.00)
            ->reason('Test iade');

        $response = $this->gateway->refund($request);

        $this->assertTrue($response->isSuccessful());
    }

    public function test_successful_query(): void
    {
        $this->httpClient->addResponse(200, [
            'ORDER_REF' => 'ORDER-PU-001',
            'REFNO' => 'PU-TXN-001',
            'ORDER_AMOUNT' => '500.00',
            'ORDER_STATUS' => 'PAYMENT_AUTHORIZED',
        ]);

        $request = QueryRequest::create()
            ->transactionId('PU-TXN-001')
            ->orderId('ORDER-PU-001');

        $response = $this->gateway->query($request);

        $this->assertTrue($response->isSuccessful());
    }

    public function test_request_sent_to_sandbox_url(): void
    {
        $this->httpClient->addResponse(200, ['STATUS' => 'SUCCESS', 'REFNO' => 'T1']);

        $this->gateway->pay(
            PaymentRequest::create()
                ->amount(100.00)
                ->orderId('ORDER-URL-TEST')
                ->card(CreditCard::create('Test', '5528790000000008', '12', '2030', '123')),
        );

        $lastRequest = $this->httpClient->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertStringContainsString('sandbox', $lastRequest['url']);
    }
}

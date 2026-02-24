<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\DTO\CreditCard;
use Arpay\DTO\Customer;
use Arpay\DTO\PaymentRequest;
use Arpay\DTO\QueryRequest;
use Arpay\DTO\RefundRequest;
use Arpay\Gateways\Odeal\OdealGateway;
use Arpay\Support\Config;
use Arpay\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Ödeal Gateway birim testleri.
 */
class OdealGatewayTest extends TestCase
{
    private OdealGateway $gateway;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->gateway = new OdealGateway();
        $this->httpClient = new MockHttpClient();

        $this->gateway->configure(new Config([
            'api_key'    => 'test_api_key',
            'secret_key' => 'test_secret_key',
            'test_mode'  => true,
        ]));
        $this->gateway->setHttpClient($this->httpClient);
    }

    public function test_gateway_name(): void
    {
        $this->assertSame('Ödeal', $this->gateway->getName());
        $this->assertSame('odeal', $this->gateway->getShortName());
    }

    public function test_supported_features(): void
    {
        $features = $this->gateway->getSupportedFeatures();
        $this->assertContains('pay', $features);
        $this->assertContains('refund', $features);
        $this->assertContains('query', $features);
    }

    public function test_successful_payment(): void
    {
        $this->httpClient->addResponse(200, [
            'status'        => 'success',
            'transactionId' => 'OD-TXN-001',
            'orderId'       => 'ORDER-OD-001',
        ]);

        $request = PaymentRequest::create()
            ->amount(175.50)
            ->currency('TRY')
            ->orderId('ORDER-OD-001')
            ->card(CreditCard::create('Test User', '5528790000000008', '12', '2030', '123'))
            ->customer(Customer::create('Test', 'User', 'test@example.com', '5551234567', '127.0.0.1'));

        $response = $this->gateway->pay($request);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('OD-TXN-001', $response->getTransactionId());
    }

    public function test_failed_payment(): void
    {
        $this->httpClient->addResponse(200, [
            'status'       => 'error',
            'errorCode'    => 'DECLINED',
            'errorMessage' => 'Kart reddedildi.',
        ]);

        $request = PaymentRequest::create()
            ->amount(175.50)
            ->orderId('ORDER-OD-002')
            ->card(CreditCard::create('Test User', '5528790000000008', '12', '2030', '123'));

        $response = $this->gateway->pay($request);

        $this->assertFalse($response->isSuccessful());
    }

    public function test_payment_without_card_fails(): void
    {
        $request = PaymentRequest::create()
            ->amount(100.00)
            ->orderId('ORDER-OD-003');

        $response = $this->gateway->pay($request);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('CARD_MISSING', $response->getErrorCode());
    }

    public function test_successful_refund(): void
    {
        $this->httpClient->addResponse(200, [
            'status'        => 'success',
            'transactionId' => 'OD-REF-001',
        ]);

        $request = RefundRequest::create()
            ->transactionId('OD-TXN-001')
            ->amount(75.00)
            ->reason('Test iade');

        $response = $this->gateway->refund($request);

        $this->assertTrue($response->isSuccessful());
    }

    public function test_successful_query(): void
    {
        $this->httpClient->addResponse(200, [
            'status'        => 'success',
            'transactionId' => 'OD-TXN-001',
            'orderId'       => 'ORDER-OD-001',
            'amount'        => 17550,
            'paymentStatus' => 'approved',
        ]);

        $request = QueryRequest::create()
            ->transactionId('OD-TXN-001')
            ->orderId('ORDER-OD-001');

        $response = $this->gateway->query($request);

        $this->assertTrue($response->isSuccessful());
    }

    public function test_request_sent_to_sandbox_url(): void
    {
        $this->httpClient->addResponse(200, ['status' => 'success', 'transactionId' => 'T1']);

        $this->gateway->pay(
            PaymentRequest::create()
                ->amount(100.00)
                ->orderId('ORDER-URL-TEST')
                ->card(CreditCard::create('Test', '5528790000000008', '12', '2030', '123'))
        );

        $lastRequest = $this->httpClient->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertStringContainsString('sandbox', $lastRequest['url']);
    }
}

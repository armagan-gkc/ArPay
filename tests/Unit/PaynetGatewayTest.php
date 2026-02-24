<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\DTO\CreditCard;
use Arpay\DTO\Customer;
use Arpay\DTO\PaymentRequest;
use Arpay\DTO\QueryRequest;
use Arpay\DTO\RefundRequest;
use Arpay\Gateways\PayNet\PaynetGateway;
use Arpay\Support\Config;
use Arpay\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Paynet Gateway birim testleri.
 *
 * @internal
 *
 * @coversNothing
 */
class PaynetGatewayTest extends TestCase
{
    private PaynetGateway $gateway;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->gateway = new PaynetGateway();
        $this->httpClient = new MockHttpClient();

        $this->gateway->configure(new Config([
            'secret_key' => 'test_secret_key',
            'merchant_id' => 'TEST_MID',
            'test_mode' => true,
        ]));
        $this->gateway->setHttpClient($this->httpClient);
    }

    public function test_gateway_name(): void
    {
        $this->assertSame('Paynet', $this->gateway->getName());
        $this->assertSame('paynet', $this->gateway->getShortName());
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
            'is_successful' => true,
            'code' => '0',
            'transaction_id' => 'PN-TXN-001',
            'order_id' => 'ORDER-PN-001',
        ]);

        $request = PaymentRequest::create()
            ->amount(400.00)
            ->currency('TRY')
            ->orderId('ORDER-PN-001')
            ->card(CreditCard::create('Test User', '5528790000000008', '12', '2030', '123'))
            ->customer(Customer::create('Test', 'User', 'test@example.com', '5551234567', '127.0.0.1'));

        $response = $this->gateway->pay($request);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('PN-TXN-001', $response->getTransactionId());
    }

    public function test_failed_payment(): void
    {
        $this->httpClient->addResponse(200, [
            'is_successful' => false,
            'code' => '99',
            'message' => 'Kart reddedildi.',
        ]);

        $request = PaymentRequest::create()
            ->amount(400.00)
            ->orderId('ORDER-PN-002')
            ->card(CreditCard::create('Test User', '5528790000000008', '12', '2030', '123'));

        $response = $this->gateway->pay($request);

        $this->assertFalse($response->isSuccessful());
    }

    public function test_payment_without_card_fails(): void
    {
        $request = PaymentRequest::create()
            ->amount(100.00)
            ->orderId('ORDER-PN-003');

        $response = $this->gateway->pay($request);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('CARD_MISSING', $response->getErrorCode());
    }

    public function test_successful_refund(): void
    {
        $this->httpClient->addResponse(200, [
            'is_successful' => true,
            'transaction_id' => 'PN-REF-001',
        ]);

        $request = RefundRequest::create()
            ->transactionId('PN-TXN-001')
            ->amount(100.00)
            ->reason('Test iade');

        $response = $this->gateway->refund($request);

        $this->assertTrue($response->isSuccessful());
    }

    public function test_successful_query(): void
    {
        $this->httpClient->addResponse(200, [
            'is_successful' => true,
            'transaction_id' => 'PN-TXN-001',
            'order_id' => 'ORDER-PN-001',
            'amount' => 40000,
            'payment_status' => 'approved',
        ]);

        $request = QueryRequest::create()
            ->transactionId('PN-TXN-001')
            ->orderId('ORDER-PN-001');

        $response = $this->gateway->query($request);

        $this->assertTrue($response->isSuccessful());
    }

    public function test_request_sent_to_sandbox_url(): void
    {
        $this->httpClient->addResponse(200, ['is_successful' => true, 'transaction_id' => 'T1']);

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

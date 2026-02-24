<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\DTO\CreditCard;
use Arpay\DTO\Customer;
use Arpay\DTO\PaymentRequest;
use Arpay\DTO\QueryRequest;
use Arpay\DTO\RefundRequest;
use Arpay\Gateways\Ipara\IparaGateway;
use Arpay\Support\Config;
use Arpay\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * iPara Gateway birim testleri.
 *
 * @internal
 *
 * @coversNothing
 */
class IparaGatewayTest extends TestCase
{
    private IparaGateway $gateway;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->gateway = new IparaGateway();
        $this->httpClient = new MockHttpClient();

        $this->gateway->configure(new Config([
            'public_key' => 'test_public_key',
            'private_key' => 'test_private_key',
            'test_mode' => true,
        ]));
        $this->gateway->setHttpClient($this->httpClient);
    }

    public function test_gateway_name(): void
    {
        $this->assertSame('iPara', $this->gateway->getName());
        $this->assertSame('ipara', $this->gateway->getShortName());
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
            'result' => '1',
            'transactionId' => 'IP-TXN-001',
            'orderId' => 'ORDER-IP-001',
        ]);

        $request = PaymentRequest::create()
            ->amount(250.00)
            ->currency('TRY')
            ->orderId('ORDER-IP-001')
            ->card(CreditCard::create('Test User', '5528790000000008', '12', '2030', '123'))
            ->customer(Customer::create('Test', 'User', 'test@example.com', '5551234567', '127.0.0.1'));

        $response = $this->gateway->pay($request);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('IP-TXN-001', $response->getTransactionId());
    }

    public function test_failed_payment(): void
    {
        $this->httpClient->addResponse(200, [
            'result' => '0',
            'errorCode' => 'DECLINED',
            'errorMessage' => 'Kart reddedildi.',
        ]);

        $request = PaymentRequest::create()
            ->amount(250.00)
            ->orderId('ORDER-IP-002')
            ->card(CreditCard::create('Test User', '5528790000000008', '12', '2030', '123'));

        $response = $this->gateway->pay($request);

        $this->assertFalse($response->isSuccessful());
    }

    public function test_payment_without_card_fails(): void
    {
        $request = PaymentRequest::create()
            ->amount(100.00)
            ->orderId('ORDER-IP-003');

        $response = $this->gateway->pay($request);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('CARD_MISSING', $response->getErrorCode());
    }

    public function test_successful_refund(): void
    {
        $this->httpClient->addResponse(200, [
            'result' => '1',
            'transactionId' => 'IP-REF-001',
        ]);

        $request = RefundRequest::create()
            ->transactionId('IP-TXN-001')
            ->amount(50.00)
            ->reason('Test iade');

        $response = $this->gateway->refund($request);

        $this->assertTrue($response->isSuccessful());
    }

    public function test_successful_query(): void
    {
        $this->httpClient->addResponse(200, [
            'result' => '1',
            'transactionId' => 'IP-TXN-001',
            'orderId' => 'ORDER-IP-001',
            'amount' => 25000,
            'status' => '1',
        ]);

        $request = QueryRequest::create()
            ->transactionId('IP-TXN-001')
            ->orderId('ORDER-IP-001');

        $response = $this->gateway->query($request);

        $this->assertTrue($response->isSuccessful());
    }

    public function test_request_sent_to_test_url(): void
    {
        $this->httpClient->addResponse(200, ['result' => '1', 'transactionId' => 'T1']);

        $this->gateway->pay(
            PaymentRequest::create()
                ->amount(100.00)
                ->orderId('ORDER-URL-TEST')
                ->card(CreditCard::create('Test', '5528790000000008', '12', '2030', '123')),
        );

        $lastRequest = $this->httpClient->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertStringContainsString('test', $lastRequest['url']);
    }
}

<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\DTO\CreditCard;
use Arpay\DTO\Customer;
use Arpay\DTO\PaymentRequest;
use Arpay\DTO\QueryRequest;
use Arpay\DTO\RefundRequest;
use Arpay\Gateways\ParamPos\ParamPosGateway;
use Arpay\Support\Config;
use Arpay\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * ParamPos Gateway birim testleri.
 *
 * @internal
 *
 * @coversNothing
 */
class ParamPosGatewayTest extends TestCase
{
    private ParamPosGateway $gateway;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->gateway = new ParamPosGateway();
        $this->httpClient = new MockHttpClient();

        $this->gateway->configure(new Config([
            'client_code' => 'TEST_CODE',
            'client_username' => 'test_user',
            'client_password' => 'test_pass',
            'guid' => 'TEST-GUID-1234',
            'test_mode' => true,
        ]));
        $this->gateway->setHttpClient($this->httpClient);
    }

    public function test_gateway_name(): void
    {
        $this->assertSame('ParamPos', $this->gateway->getName());
        $this->assertSame('parampos', $this->gateway->getShortName());
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
            'Sonuc' => '1',
            'Sonuc_Str' => '00',
            'Dekont_ID' => 'PP-TXN-001',
        ]);

        $request = PaymentRequest::create()
            ->amount(300.00)
            ->currency('TRY')
            ->orderId('ORDER-PP-001')
            ->card(CreditCard::create('Test User', '5528790000000008', '12', '2030', '123'))
            ->customer(Customer::create('Test', 'User', 'test@example.com', '5551234567', '127.0.0.1'));

        $response = $this->gateway->pay($request);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('PP-TXN-001', $response->getTransactionId());
    }

    public function test_failed_payment(): void
    {
        $this->httpClient->addResponse(200, [
            'Sonuc' => '0',
            'Sonuc_Str' => 'DECLINED',
            'Sonuc_Ack' => 'Kart reddedildi.',
        ]);

        $request = PaymentRequest::create()
            ->amount(300.00)
            ->orderId('ORDER-PP-002')
            ->card(CreditCard::create('Test User', '5528790000000008', '12', '2030', '123'));

        $response = $this->gateway->pay($request);

        $this->assertFalse($response->isSuccessful());
    }

    public function test_payment_without_card_fails(): void
    {
        $request = PaymentRequest::create()
            ->amount(100.00)
            ->orderId('ORDER-PP-003');

        $response = $this->gateway->pay($request);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('CARD_MISSING', $response->getErrorCode());
    }

    public function test_successful_refund(): void
    {
        $this->httpClient->addResponse(200, [
            'Sonuc' => '1',
            'Dekont_ID' => 'PP-REF-001',
        ]);

        $request = RefundRequest::create()
            ->transactionId('PP-TXN-001')
            ->amount(100.00)
            ->reason('Test iade');

        $response = $this->gateway->refund($request);

        $this->assertTrue($response->isSuccessful());
    }

    public function test_successful_query(): void
    {
        $this->httpClient->addResponse(200, [
            'Sonuc' => '1',
            'Dekont_ID' => 'PP-TXN-001',
            'Siparis_ID' => 'ORDER-PP-001',
            'Tutar' => '30000',
        ]);

        $request = QueryRequest::create()
            ->transactionId('PP-TXN-001')
            ->orderId('ORDER-PP-001');

        $response = $this->gateway->query($request);

        $this->assertTrue($response->isSuccessful());
    }

    public function test_request_sent_to_test_url(): void
    {
        $this->httpClient->addResponse(200, ['Sonuc' => '1', 'Dekont_ID' => 'T1']);

        $this->gateway->pay(
            PaymentRequest::create()
                ->amount(100.00)
                ->orderId('ORDER-URL-TEST')
                ->card(CreditCard::create('Test', '5528790000000008', '12', '2030', '123')),
        );

        $lastRequest = $this->httpClient->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertStringContainsString('test-pos', $lastRequest['url']);
    }
}

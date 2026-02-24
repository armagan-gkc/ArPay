<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\DTO\CreditCard;
use Arpay\DTO\Customer;
use Arpay\DTO\PaymentRequest;
use Arpay\Gateways\Iyzico\IyzicoGateway;
use Arpay\Support\Config;
use Arpay\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Iyzico Gateway birim testleri (sahte HTTP istemci ile).
 */
class IyzicoGatewayTest extends TestCase
{
    private IyzicoGateway $gateway;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->gateway = new IyzicoGateway();
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
        $this->assertSame('Iyzico', $this->gateway->getName());
        $this->assertSame('iyzico', $this->gateway->getShortName());
    }

    public function test_successful_payment(): void
    {
        $this->httpClient->addResponse(200, [
            'status'        => 'success',
            'paymentId'     => 'IYZ-001',
            'conversationId' => 'ORDER-001',
            'price'         => '150.00',
        ]);

        $request = PaymentRequest::create()
            ->amount(150.00)
            ->currency('TRY')
            ->orderId('ORDER-001')
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
            ));

        $response = $this->gateway->pay($request);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('IYZ-001', $response->getTransactionId());
    }

    public function test_failed_payment(): void
    {
        $this->httpClient->addResponse(200, [
            'status'       => 'failure',
            'errorCode'    => '12',
            'errorMessage' => 'Yetersiz bakiye.',
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
            ))
            ->customer(Customer::create(
                firstName: 'Test',
                lastName: 'User',
                email: 'test@example.com',
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

    public function test_request_sent_to_sandbox_url(): void
    {
        $this->httpClient->addResponse(200, ['status' => 'success', 'paymentId' => 'P1']);

        $this->gateway->pay(
            PaymentRequest::create()
                ->amount(100.00)
                ->orderId('ORDER-URL')
                ->card(CreditCard::create(
                    cardHolderName: 'Test',
                    cardNumber: '5528790000000008',
                    expireMonth: '12',
                    expireYear: '2030',
                    cvv: '123',
                ))
                ->customer(Customer::create(
                    firstName: 'Test',
                    lastName: 'User',
                    email: 'test@example.com',
                ))
        );

        $lastRequest = $this->httpClient->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertStringContainsString('sandbox', $lastRequest['url']);
    }
}

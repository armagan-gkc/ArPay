<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\DTO\PaymentResponse;
use Arpay\Enums\PaymentStatus;
use PHPUnit\Framework\TestCase;

/**
 * PaymentResponse DTO birim testleri.
 *
 * @internal
 *
 * @coversNothing
 */
class PaymentResponseTest extends TestCase
{
    public function test_successful_response(): void
    {
        $response = PaymentResponse::successful(
            transactionId: 'TXN-123',
            orderId: 'ORDER-001',
            amount: 150.00,
            rawResponse: ['status' => 'ok'],
        );

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('TXN-123', $response->getTransactionId());
        $this->assertSame('ORDER-001', $response->getOrderId());
        $this->assertSame(150.00, $response->getAmount());
        $this->assertSame(PaymentStatus::Successful, $response->getPaymentStatus());
        $this->assertSame('', $response->getErrorCode());
        $this->assertSame('', $response->getErrorMessage());
        $this->assertSame(['status' => 'ok'], $response->getRawResponse());
    }

    public function test_failed_response(): void
    {
        $response = PaymentResponse::failed(
            errorCode: 'DECLINED',
            errorMessage: 'Kart reddedildi.',
            rawResponse: ['error' => 'declined'],
        );

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('DECLINED', $response->getErrorCode());
        $this->assertSame('Kart reddedildi.', $response->getErrorMessage());
        $this->assertSame(PaymentStatus::Failed, $response->getPaymentStatus());
        $this->assertSame('', $response->getTransactionId());
    }
}

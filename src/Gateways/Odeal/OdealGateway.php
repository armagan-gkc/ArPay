<?php

declare(strict_types=1);

namespace Arpay\Gateways\Odeal;

use Arpay\Contracts\PayableInterface;
use Arpay\Contracts\QueryableInterface;
use Arpay\Contracts\RefundableInterface;
use Arpay\Contracts\SecurePayableInterface;
use Arpay\DTO\PaymentRequest;
use Arpay\DTO\PaymentResponse;
use Arpay\DTO\QueryRequest;
use Arpay\DTO\QueryResponse;
use Arpay\DTO\RefundRequest;
use Arpay\DTO\RefundResponse;
use Arpay\DTO\SecureCallbackData;
use Arpay\DTO\SecureInitResponse;
use Arpay\DTO\SecurePaymentRequest;
use Arpay\Enums\PaymentStatus;
use Arpay\Gateways\AbstractGateway;
use Arpay\Support\HashGenerator;
use Arpay\Support\MoneyFormatter;

/**
 * Ödeal ödeme altyapısı gateway implementasyonu.
 *
 * Ödeal, basit ve kolay entegre edilebilir bir Türk ödeme altyapısıdır.
 * Abonelik ve taksit sorgulama desteği bulunmamaktadır.
 *
 * Yapılandırma:
 * ```php
 * $gateway = Arpay::create('odeal', [
 *     'api_key'    => 'XXXXX',
 *     'secret_key' => 'YYYYY',
 * ]);
 * ```
 *
 * @author Armağan Gökce
 */
class OdealGateway extends AbstractGateway implements PayableInterface, RefundableInterface, QueryableInterface, SecurePayableInterface
{
    private const LIVE_BASE_URL = 'https://api.odeal.com/v1';
    private const SANDBOX_BASE_URL = 'https://sandbox-api.odeal.com/v1';

    public function getName(): string
    {
        return 'Ödeal';
    }

    public function getShortName(): string
    {
        return 'odeal';
    }

    public function getSupportedFeatures(): array
    {
        return ['pay', 'payInstallment', 'refund', 'query', '3dsecure'];
    }

    public function pay(PaymentRequest $request): PaymentResponse
    {
        $card = $request->getCard();
        if (null === $card) {
            return PaymentResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $customer = $request->getCustomer();

        $body = [
            'amount' => MoneyFormatter::toDecimalString($request->getAmount()),
            'currency' => $request->getCurrency(),
            'orderId' => $request->getOrderId(),
            'description' => $request->getDescription(),
            'installment' => $request->getInstallmentCount(),
            'card' => [
                'holderName' => $card->cardHolderName,
                'number' => $card->cardNumber,
                'expMonth' => $card->expireMonth,
                'expYear' => $card->expireYear,
                'cvv' => $card->cvv,
            ],
            'buyer' => [
                'name' => $customer?->firstName ?? '',
                'surname' => $customer?->lastName ?? '',
                'email' => $customer?->email ?? '',
                'ip' => $customer?->ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            ],
            'signature' => $this->generateSignature([
                $request->getOrderId(),
                MoneyFormatter::toDecimalString($request->getAmount()),
                $request->getCurrency(),
            ]),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/auth',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['status'] ?? '') === 'success') {
            return PaymentResponse::successful(
                transactionId: $this->toString($data['transactionId'] ?? ''),
                orderId: $this->toString($data['orderId'] ?? $request->getOrderId()),
                amount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return PaymentResponse::failed(
            errorCode: $this->toString($data['errorCode'] ?? 'UNKNOWN'),
            errorMessage: $this->toString($data['errorMessage'] ?? 'Ödeal ödeme başarısız.'),
            rawResponse: $data,
        );
    }

    public function payInstallment(PaymentRequest $request): PaymentResponse
    {
        return $this->pay($request);
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $body = [
            'transactionId' => $request->getTransactionId(),
            'orderId' => $request->getOrderId(),
            'amount' => MoneyFormatter::toDecimalString($request->getAmount()),
            'reason' => $request->getReason(),
            'signature' => $this->generateSignature([
                $request->getTransactionId() ?: $request->getOrderId(),
                MoneyFormatter::toDecimalString($request->getAmount()),
            ]),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/refund',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['status'] ?? '') === 'success') {
            return RefundResponse::successful(
                transactionId: $this->toString($data['transactionId'] ?? $request->getTransactionId()),
                refundedAmount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return RefundResponse::failed(
            errorCode: $this->toString($data['errorCode'] ?? 'UNKNOWN'),
            errorMessage: $this->toString($data['errorMessage'] ?? 'Ödeal iade başarısız.'),
            rawResponse: $data,
        );
    }

    public function query(QueryRequest $request): QueryResponse
    {
        $body = [
            'transactionId' => $request->getTransactionId(),
            'orderId' => $request->getOrderId(),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/detail',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['status'] ?? '') === 'success') {
            $paymentStatus = match ($data['paymentStatus'] ?? '') {
                'approved', 'captured' => PaymentStatus::Successful,
                'declined', 'error' => PaymentStatus::Failed,
                'pending' => PaymentStatus::Pending,
                'refunded' => PaymentStatus::Refunded,
                'cancelled' => PaymentStatus::Cancelled,
                default => PaymentStatus::Pending,
            };

            return QueryResponse::successful(
                transactionId: $this->toString($data['transactionId'] ?? ''),
                orderId: $this->toString($data['orderId'] ?? ''),
                amount: $this->toFloat($data['amount'] ?? 0),
                status: $paymentStatus,
                rawResponse: $data,
            );
        }

        return QueryResponse::failed(
            errorCode: $this->toString($data['errorCode'] ?? 'UNKNOWN'),
            errorMessage: $this->toString($data['errorMessage'] ?? 'Ödeal sorgu başarısız.'),
            rawResponse: $data,
        );
    }

    public function initSecurePayment(SecurePaymentRequest $request): SecureInitResponse
    {
        $card = $request->getCard();
        if (null === $card) {
            return SecureInitResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $customer = $request->getCustomer();

        $body = [
            'amount' => MoneyFormatter::toDecimalString($request->getAmount()),
            'currency' => $request->getCurrency(),
            'orderId' => $request->getOrderId(),
            'description' => $request->getDescription(),
            'installment' => $request->getInstallmentCount(),
            'callbackUrl' => $request->getSuccessUrl() ?: $request->getCallbackUrl(),
            'failUrl' => $request->getFailUrl() ?: $request->getCallbackUrl(),
            'card' => [
                'holderName' => $card->cardHolderName,
                'number' => $card->cardNumber,
                'expMonth' => $card->expireMonth,
                'expYear' => $card->expireYear,
                'cvv' => $card->cvv,
            ],
            'buyer' => [
                'name' => $customer?->firstName ?? '',
                'surname' => $customer?->lastName ?? '',
                'email' => $customer?->email ?? '',
                'ip' => $customer?->ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            ],
            'signature' => $this->generateSignature([
                $request->getOrderId(),
                MoneyFormatter::toDecimalString($request->getAmount()),
                $request->getCurrency(),
            ]),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/3dsecure/init',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (isset($data['threeDSecureHtml'])) {
            $decoded = base64_decode($this->toString($data['threeDSecureHtml']), true);
            $html = false !== $decoded ? $decoded : '';

            return SecureInitResponse::html(
                $html,
                $data,
            );
        }

        if (isset($data['redirectUrl'])) {
            return SecureInitResponse::redirect($this->toString($data['redirectUrl']), [], $data);
        }

        return SecureInitResponse::failed(
            errorCode: $this->toString($data['errorCode'] ?? 'UNKNOWN'),
            errorMessage: $this->toString($data['errorMessage'] ?? 'Ödeal 3D Secure başlatma başarısız.'),
            rawResponse: $data,
        );
    }

    public function completeSecurePayment(SecureCallbackData $data): PaymentResponse
    {
        $status = $data->get('status', $data->get('mdStatus', ''));

        if ('success' === $status || '1' === $status) {
            $body = [
                'paymentToken' => $data->get('paymentToken', ''),
                'orderId' => $data->get('orderId', ''),
            ];

            $response = $this->httpClient->post(
                $this->getActiveBaseUrl() . '/payment/3dsecure/complete',
                $this->buildHeaders(),
                json_encode($body, JSON_THROW_ON_ERROR),
            );
            $responseData = $response->toArray();

            if (($responseData['status'] ?? '') === 'success') {
                return PaymentResponse::successful(
                    transactionId: $this->toString($responseData['transactionId'] ?? ''),
                    orderId: $this->toString($responseData['orderId'] ?? $data->get('orderId', '')),
                    amount: $this->toFloat($responseData['amount'] ?? 0),
                    rawResponse: $responseData,
                );
            }

            return PaymentResponse::failed(
                errorCode: $this->toString($responseData['errorCode'] ?? 'UNKNOWN'),
                errorMessage: $this->toString($responseData['errorMessage'] ?? 'Ödeal 3D Secure ödeme tamamlama başarısız.'),
                rawResponse: $responseData,
            );
        }

        return PaymentResponse::failed(
            errorCode: $this->toString($data->get('errorCode', 'UNKNOWN')),
            errorMessage: $this->toString($data->get('errorMessage', 'Ödeal 3D Secure doğrulama başarısız.')),
            rawResponse: $data->toArray(),
        );
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['api_key', 'secret_key'];
    }

    protected function getBaseUrl(): string
    {
        return self::LIVE_BASE_URL;
    }

    protected function getTestBaseUrl(): string
    {
        return self::SANDBOX_BASE_URL;
    }

    /**
     * Ödeal API istekleri için standart başlıkları oluşturur.
     *
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        $apiKey = $this->toString($this->config->get('api_key'));

        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
            'X-Api-Key' => $apiKey,
        ];
    }

    /**
     * Ödeal imza doğrulaması için hash oluşturur.
     *
     * @param array<int, string> $params
     */
    private function generateSignature(array $params): string
    {
        $hashString = implode('', $params) . $this->config->get('secret_key');

        return HashGenerator::sha256($hashString);
    }

    private function toString(mixed $value, string $default = ''): string
    {
        return is_string($value) ? $value : (is_numeric($value) ? (string) $value : $default);
    }

    private function toFloat(mixed $value, float $default = 0.0): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }

    /** @phpstan-ignore method.unused */
    private function toInt(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }
}

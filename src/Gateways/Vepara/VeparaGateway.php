<?php

declare(strict_types=1);

namespace Arpay\Gateways\Vepara;

use Arpay\Contracts\InstallmentQueryableInterface;
use Arpay\Contracts\PayableInterface;
use Arpay\Contracts\QueryableInterface;
use Arpay\Contracts\RefundableInterface;
use Arpay\Contracts\SecurePayableInterface;
use Arpay\DTO\InstallmentInfo;
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
 * Vepara ödeme altyapısı gateway implementasyonu.
 *
 * Vepara, REST tabanlı JSON API kullanan bir Türk ödeme altyapısıdır.
 * 3D Secure, taksitli ödeme, iade ve sorgu destekler. Abonelik desteği yoktur.
 *
 * Yapılandırma:
 * ```php
 * $gateway = Arpay::create('vepara', [
 *     'api_key'     => 'XXXXX',
 *     'secret_key'  => 'YYYYY',
 *     'merchant_id' => '12345',
 * ]);
 * ```
 *
 * @author Armağan Gökce
 */
class VeparaGateway extends AbstractGateway implements PayableInterface, RefundableInterface, QueryableInterface, SecurePayableInterface, InstallmentQueryableInterface
{
    /** @var string Canlı API URL'si */
    private const LIVE_BASE_URL = 'https://api.vepara.com.tr/v2';

    /** @var string Sandbox API URL'si */
    private const SANDBOX_BASE_URL = 'https://sandbox-api.vepara.com.tr/v2';

    public function getName(): string
    {
        return 'Vepara';
    }

    public function getShortName(): string
    {
        return 'vepara';
    }

    public function getSupportedFeatures(): array
    {
        return ['pay', 'payInstallment', 'refund', 'query', '3dsecure', 'installmentQuery'];
    }

    /**
     * Ödeme yapar.
     *
     * {@inheritdoc}
     */
    public function pay(PaymentRequest $request): PaymentResponse
    {
        $card = $request->getCard();
        if (null === $card) {
            return PaymentResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $body = [
            'merchant_id' => $this->config->get('merchant_id'),
            'order_id' => $request->getOrderId(),
            'amount' => MoneyFormatter::toDecimalString($request->getAmount()),
            'currency' => $request->getCurrency(),
            'installment' => $request->getInstallmentCount(),
            'card_holder_name' => $card->cardHolderName,
            'card_number' => $card->cardNumber,
            'expire_month' => $card->expireMonth,
            'expire_year' => $card->expireYear,
            'cvv' => $card->cvv,
            'description' => $request->getDescription(),
        ];

        $headers = $this->buildHeaders($body);

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/non3d',
            $headers,
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['status_code'] ?? 0) === 100) {
            return PaymentResponse::successful(
                transactionId: $data['transaction_id'] ?? '',
                orderId: $request->getOrderId(),
                amount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return PaymentResponse::failed(
            errorCode: (string) ($data['status_code'] ?? 'UNKNOWN'),
            errorMessage: $data['status_description'] ?? 'Vepara ödeme başarısız.',
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
            'merchant_id' => $this->config->get('merchant_id'),
            'transaction_id' => $request->getTransactionId() ?: $request->getOrderId(),
            'amount' => MoneyFormatter::toDecimalString($request->getAmount()),
        ];

        $headers = $this->buildHeaders($body);

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/refund',
            $headers,
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['status_code'] ?? 0) === 100) {
            return RefundResponse::successful(
                transactionId: $data['transaction_id'] ?? $request->getTransactionId(),
                refundedAmount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return RefundResponse::failed(
            errorCode: (string) ($data['status_code'] ?? 'UNKNOWN'),
            errorMessage: $data['status_description'] ?? 'Vepara iade başarısız.',
            rawResponse: $data,
        );
    }

    public function query(QueryRequest $request): QueryResponse
    {
        $body = [
            'merchant_id' => $this->config->get('merchant_id'),
            'transaction_id' => $request->getTransactionId() ?: $request->getOrderId(),
        ];

        $headers = $this->buildHeaders($body);

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/query',
            $headers,
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['status_code'] ?? 0) === 100) {
            return QueryResponse::successful(
                transactionId: $data['transaction_id'] ?? '',
                orderId: $data['order_id'] ?? '',
                amount: (float) ($data['amount'] ?? 0),
                status: PaymentStatus::Successful,
                rawResponse: $data,
            );
        }

        return QueryResponse::failed(
            errorCode: (string) ($data['status_code'] ?? 'UNKNOWN'),
            errorMessage: $data['status_description'] ?? 'Vepara sorgu başarısız.',
            rawResponse: $data,
        );
    }

    public function initSecurePayment(SecurePaymentRequest $request): SecureInitResponse
    {
        $card = $request->getCard();
        if (null === $card) {
            return SecureInitResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $body = [
            'merchant_id' => $this->config->get('merchant_id'),
            'order_id' => $request->getOrderId(),
            'amount' => MoneyFormatter::toDecimalString($request->getAmount()),
            'currency' => $request->getCurrency(),
            'installment' => $request->getInstallmentCount(),
            'card_holder_name' => $card->cardHolderName,
            'card_number' => $card->cardNumber,
            'expire_month' => $card->expireMonth,
            'expire_year' => $card->expireYear,
            'cvv' => $card->cvv,
            'callback_url' => $request->getCallbackUrl(),
        ];

        $headers = $this->buildHeaders($body);

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/3d',
            $headers,
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['status_code'] ?? 0) === 100 && isset($data['redirect_url'])) {
            return SecureInitResponse::redirect(
                $data['redirect_url'],
                $data['form_data'] ?? [],
                $data,
            );
        }

        if (isset($data['html_content'])) {
            return SecureInitResponse::html($data['html_content'], $data);
        }

        return SecureInitResponse::failed(
            errorCode: (string) ($data['status_code'] ?? 'UNKNOWN'),
            errorMessage: $data['status_description'] ?? 'Vepara 3D Secure başlatma başarısız.',
            rawResponse: $data,
        );
    }

    public function completeSecurePayment(SecureCallbackData $data): PaymentResponse
    {
        $status = $data->get('status', '');
        $transactionId = (string) $data->get('transaction_id', '');
        $orderId = (string) $data->get('order_id', '');

        if ('success' === $status || '1' === $status) {
            return PaymentResponse::successful(
                transactionId: $transactionId,
                orderId: $orderId,
                amount: (float) $data->get('amount', 0),
                rawResponse: $data->toArray(),
            );
        }

        return PaymentResponse::failed(
            errorCode: (string) ($data->get('error_code', 'UNKNOWN')),
            errorMessage: (string) $data->get('error_message', 'Vepara 3D Secure ödeme başarısız.'),
            rawResponse: $data->toArray(),
        );
    }

    public function queryInstallments(string $binNumber, float $amount): array
    {
        $body = [
            'merchant_id' => $this->config->get('merchant_id'),
            'bin_number' => $binNumber,
            'amount' => MoneyFormatter::toDecimalString($amount),
        ];

        $headers = $this->buildHeaders($body);

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/installment',
            $headers,
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        $installments = [];

        foreach ($data['installments'] ?? [] as $inst) {
            $count = (int) ($inst['count'] ?? 0);
            if ($count > 0) {
                $installments[] = InstallmentInfo::create(
                    count: $count,
                    perInstallment: (float) ($inst['per_amount'] ?? 0),
                    total: (float) ($inst['total_amount'] ?? 0),
                    interestRate: (float) ($inst['interest_rate'] ?? 0),
                );
            }
        }

        return $installments;
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['api_key', 'secret_key', 'merchant_id'];
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
     * API istekleri için gerekli header'ları oluşturur.
     *
     * @param array<string, mixed> $body İstek gövdesi
     *
     * @return array<string, string> HTTP başlıkları
     */
    private function buildHeaders(array $body): array
    {
        $bodyStr = json_encode($body, JSON_THROW_ON_ERROR);
        $hash = HashGenerator::hmacSha256($bodyStr, $this->config->get('secret_key'));

        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->config->get('api_key'),
            'X-Hash' => $hash,
        ];
    }
}

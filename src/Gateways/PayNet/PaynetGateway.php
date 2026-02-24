<?php

declare(strict_types=1);

namespace Arpay\Gateways\PayNet;

use Arpay\Contracts\InstallmentQueryableInterface;
use Arpay\Contracts\PayableInterface;
use Arpay\Contracts\QueryableInterface;
use Arpay\Contracts\RefundableInterface;
use Arpay\Contracts\SecurePayableInterface;
use Arpay\Contracts\SubscribableInterface;
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
use Arpay\DTO\SubscriptionRequest;
use Arpay\DTO\SubscriptionResponse;
use Arpay\Enums\PaymentStatus;
use Arpay\Gateways\AbstractGateway;
use Arpay\Support\HashGenerator;
use Arpay\Support\MoneyFormatter;

/**
 * Paynet ödeme altyapısı gateway implementasyonu.
 *
 * Paynet, Türkiye'de kullanılan bir sanal POS altyapısıdır.
 * Tüm ödeme özelliklerini (ödeme, iade, sorgu, 3D Secure, abonelik, taksit) destekler.
 *
 * Not: Paynet, Ocak 2026'da Iyzico ile birleşmiştir.
 *
 * Yapılandırma:
 * ```php
 * $gateway = Arpay::create('paynet', [
 *     'secret_key'  => 'XXXXX',
 *     'merchant_id' => 'YYYYY',
 * ]);
 * ```
 *
 * @author Armağan Gökce
 */
class PaynetGateway extends AbstractGateway implements PayableInterface, RefundableInterface, QueryableInterface, SecurePayableInterface, SubscribableInterface, InstallmentQueryableInterface
{
    private const LIVE_BASE_URL = 'https://api.paynet.com.tr/v2';
    private const SANDBOX_BASE_URL = 'https://sandbox-api.paynet.com.tr/v2';

    public function getName(): string
    {
        return 'Paynet';
    }

    public function getShortName(): string
    {
        return 'paynet';
    }

    public function getSupportedFeatures(): array
    {
        return ['pay', 'payInstallment', 'refund', 'query', '3dsecure', 'subscription', 'installmentQuery'];
    }

    public function pay(PaymentRequest $request): PaymentResponse
    {
        $card = $request->getCard();
        if (null === $card) {
            return PaymentResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $customer = $request->getCustomer();

        $body = [
            'merchant_id' => $this->config->get('merchant_id'),
            'amount' => MoneyFormatter::toPenny($request->getAmount()),
            'currency' => $request->getCurrency(),
            'order_id' => $request->getOrderId(),
            'description' => $request->getDescription(),
            'installment' => $request->getInstallmentCount(),
            'card_holder' => $card->cardHolderName,
            'card_no' => $card->cardNumber,
            'card_exp_month' => $card->expireMonth,
            'card_exp_year' => $card->expireYear,
            'card_cvv' => $card->cvv,
            'customer_name' => $customer?->firstName ?? '',
            'customer_surname' => $customer?->lastName ?? '',
            'customer_email' => $customer?->email ?? '',
            'customer_ip' => $customer?->ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'hash' => $this->generateHash(
                $request->getOrderId(),
                (string) MoneyFormatter::toPenny($request->getAmount()),
                $request->getCurrency(),
            ),
        ];

        // Sepet kalemleri
        $products = [];
        foreach ($request->getCartItems() as $item) {
            $products[] = [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'price' => MoneyFormatter::toPenny($item->price),
                'quantity' => $item->quantity,
            ];
        }
        if (!empty($products)) {
            $body['products'] = $products;
        }

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/sale',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['is_successful'] ?? false) === true || ($data['code'] ?? '') === '0') {
            return PaymentResponse::successful(
                transactionId: $this->toString($data['transaction_id'] ?? ''),
                orderId: $this->toString($data['order_id'] ?? $request->getOrderId()),
                amount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return PaymentResponse::failed(
            errorCode: $this->toString($data['code'] ?? 'UNKNOWN'),
            errorMessage: $this->toString($data['message'] ?? 'Paynet ödeme başarısız.'),
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
            'transaction_id' => $request->getTransactionId(),
            'order_id' => $request->getOrderId(),
            'amount' => MoneyFormatter::toPenny($request->getAmount()),
            'reason' => $request->getReason(),
            'hash' => $this->generateHash(
                $request->getTransactionId() ?: $request->getOrderId(),
                (string) MoneyFormatter::toPenny($request->getAmount()),
            ),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/refund',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['is_successful'] ?? false) === true) {
            return RefundResponse::successful(
                transactionId: $this->toString($data['transaction_id'] ?? $request->getTransactionId()),
                refundedAmount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return RefundResponse::failed(
            errorCode: $this->toString($data['code'] ?? 'UNKNOWN'),
            errorMessage: $this->toString($data['message'] ?? 'Paynet iade başarısız.'),
            rawResponse: $data,
        );
    }

    public function query(QueryRequest $request): QueryResponse
    {
        $body = [
            'merchant_id' => $this->config->get('merchant_id'),
            'transaction_id' => $request->getTransactionId(),
            'order_id' => $request->getOrderId(),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/inquiry',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['is_successful'] ?? false) === true) {
            $status = match ($data['payment_status'] ?? '') {
                'approved', 'captured' => PaymentStatus::Successful,
                'declined', 'error' => PaymentStatus::Failed,
                'pending' => PaymentStatus::Pending,
                'refunded' => PaymentStatus::Refunded,
                'cancelled' => PaymentStatus::Cancelled,
                default => PaymentStatus::Pending,
            };

            return QueryResponse::successful(
                transactionId: $this->toString($data['transaction_id'] ?? ''),
                orderId: $this->toString($data['order_id'] ?? ''),
                amount: MoneyFormatter::toFloat($this->toInt($data['amount'] ?? 0)),
                status: $status,
                rawResponse: $data,
            );
        }

        return QueryResponse::failed(
            errorCode: $this->toString($data['code'] ?? 'UNKNOWN'),
            errorMessage: $this->toString($data['message'] ?? 'Paynet sorgu başarısız.'),
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
            'merchant_id' => $this->config->get('merchant_id'),
            'amount' => MoneyFormatter::toPenny($request->getAmount()),
            'currency' => $request->getCurrency(),
            'order_id' => $request->getOrderId(),
            'description' => $request->getDescription(),
            'installment' => $request->getInstallmentCount(),
            'card_holder' => $card->cardHolderName,
            'card_no' => $card->cardNumber,
            'card_exp_month' => $card->expireMonth,
            'card_exp_year' => $card->expireYear,
            'card_cvv' => $card->cvv,
            'customer_name' => $customer?->firstName ?? '',
            'customer_email' => $customer?->email ?? '',
            'customer_ip' => $customer?->ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'success_url' => $request->getSuccessUrl() ?: $request->getCallbackUrl(),
            'fail_url' => $request->getFailUrl() ?: $request->getCallbackUrl(),
            'hash' => $this->generateHash(
                $request->getOrderId(),
                (string) MoneyFormatter::toPenny($request->getAmount()),
                $request->getCurrency(),
            ),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/3d/init',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (isset($data['html_content'])) {
            return SecureInitResponse::html($this->toString($data['html_content']), $data);
        }

        if (isset($data['redirect_url'])) {
            return SecureInitResponse::redirect($this->toString($data['redirect_url']), [], $data);
        }

        return SecureInitResponse::failed(
            errorCode: $this->toString($data['code'] ?? 'UNKNOWN'),
            errorMessage: $this->toString($data['message'] ?? 'Paynet 3D Secure başlatma başarısız.'),
            rawResponse: $data,
        );
    }

    public function completeSecurePayment(SecureCallbackData $data): PaymentResponse
    {
        $mdStatus = $data->get('md_status', $data->get('mdStatus', ''));

        if ('1' === $mdStatus) {
            $body = [
                'merchant_id' => $this->config->get('merchant_id'),
                'payment_token' => $data->get('payment_token', $data->get('token', '')),
                'order_id' => $data->get('order_id', ''),
            ];

            $response = $this->httpClient->post(
                $this->getActiveBaseUrl() . '/payment/3d/complete',
                $this->buildHeaders(),
                json_encode($body, JSON_THROW_ON_ERROR),
            );
            $responseData = $response->toArray();

            if (($responseData['is_successful'] ?? false) === true) {
                return PaymentResponse::successful(
                    transactionId: $this->toString($responseData['transaction_id'] ?? ''),
                    orderId: $this->toString($responseData['order_id'] ?? $data->get('order_id', '')),
                    amount: MoneyFormatter::toFloat($this->toInt($responseData['amount'] ?? 0)),
                    rawResponse: $responseData,
                );
            }

            return PaymentResponse::failed(
                errorCode: $this->toString($responseData['code'] ?? 'UNKNOWN'),
                errorMessage: $this->toString($responseData['message'] ?? 'Paynet 3D Secure ödeme tamamlama başarısız.'),
                rawResponse: $responseData,
            );
        }

        return PaymentResponse::failed(
            errorCode: $this->toString($data->get('code', 'UNKNOWN')),
            errorMessage: $this->toString($data->get('message', 'Paynet 3D Secure doğrulama başarısız.')),
            rawResponse: $data->toArray(),
        );
    }

    public function createSubscription(SubscriptionRequest $request): SubscriptionResponse
    {
        $card = $request->getCard();
        if (null === $card) {
            return SubscriptionResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $customer = $request->getCustomer();

        $body = [
            'merchant_id' => $this->config->get('merchant_id'),
            'plan_name' => $request->getPlanName(),
            'amount' => MoneyFormatter::toPenny($request->getAmount()),
            'currency' => $request->getCurrency(),
            'period' => $request->getPeriod(),
            'period_interval' => $request->getPeriodInterval(),
            'card_holder' => $card->cardHolderName,
            'card_no' => $card->cardNumber,
            'card_exp_month' => $card->expireMonth,
            'card_exp_year' => $card->expireYear,
            'card_cvv' => $card->cvv,
            'customer_name' => $customer?->firstName ?? '',
            'customer_surname' => $customer?->lastName ?? '',
            'customer_email' => $customer?->email ?? '',
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/subscription/create',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['is_successful'] ?? false) === true) {
            return SubscriptionResponse::successful(
                subscriptionId: $this->toString($data['subscription_id'] ?? ''),
                rawResponse: $data,
            );
        }

        return SubscriptionResponse::failed(
            errorCode: $this->toString($data['code'] ?? 'UNKNOWN'),
            errorMessage: $this->toString($data['message'] ?? 'Paynet abonelik oluşturma başarısız.'),
            rawResponse: $data,
        );
    }

    public function cancelSubscription(string $subscriptionId): SubscriptionResponse
    {
        $body = [
            'merchant_id' => $this->config->get('merchant_id'),
            'subscription_id' => $subscriptionId,
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/subscription/cancel',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['is_successful'] ?? false) === true) {
            return SubscriptionResponse::successful($subscriptionId, 'cancelled', $data);
        }

        return SubscriptionResponse::failed(
            errorCode: $this->toString($data['code'] ?? 'UNKNOWN'),
            errorMessage: $this->toString($data['message'] ?? 'Paynet abonelik iptali başarısız.'),
            rawResponse: $data,
        );
    }

    public function queryInstallments(string $binNumber, float $amount): array
    {
        $body = [
            'merchant_id' => $this->config->get('merchant_id'),
            'bin' => $binNumber,
            'amount' => MoneyFormatter::toPenny($amount),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/installment-query',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        $installments = [];
        $installmentList = $data['installment_list'] ?? [];
        if (is_array($installmentList)) {
            foreach ($installmentList as $inst) {
                if (!is_array($inst)) {
                    continue;
                }
                $count = $this->toInt($inst['count'] ?? 0);
                if ($count > 0) {
                    $installments[] = InstallmentInfo::create(
                        count: $count,
                        perInstallment: MoneyFormatter::toFloat($this->toInt($inst['per_amount'] ?? 0)),
                        total: MoneyFormatter::toFloat($this->toInt($inst['total_amount'] ?? 0)),
                        interestRate: $this->toFloat($inst['interest_rate'] ?? 0),
                    );
                }
            }
        }

        return $installments;
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['secret_key', 'merchant_id'];
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
     * Paynet API istekleri için standart başlıkları oluşturur.
     *
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(
                $this->config->get('merchant_id') . ':' . $this->config->get('secret_key'),
            ),
        ];
    }

    /**
     * İstek parametrelerinden güvenlik hash'i oluşturur.
     */
    private function generateHash(string ...$params): string
    {
        $hashStr = implode('', $params) . $this->config->get('secret_key');

        return HashGenerator::sha256($hashStr);
    }

    private function toString(mixed $value, string $default = ''): string
    {
        return is_string($value) ? $value : (is_numeric($value) ? (string) $value : $default);
    }

    private function toFloat(mixed $value, float $default = 0.0): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }

    private function toInt(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }
}

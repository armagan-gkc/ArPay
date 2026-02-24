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
     */
    private function buildHeaders(): array
    {
        return [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(
                $this->config->get('merchant_id') . ':' . $this->config->get('secret_key')
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

    public function pay(PaymentRequest $request): PaymentResponse
    {
        $card = $request->getCard();
        if ($card === null) {
            return PaymentResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $customer = $request->getCustomer();

        $body = [
            'merchant_id'   => $this->config->get('merchant_id'),
            'amount'        => MoneyFormatter::toPenny($request->getAmount()),
            'currency'      => $request->getCurrency(),
            'order_id'      => $request->getOrderId(),
            'description'   => $request->getDescription(),
            'installment'   => $request->getInstallmentCount(),
            'card_holder'   => $card->cardHolderName,
            'card_no'       => $card->cardNumber,
            'card_exp_month' => $card->expireMonth,
            'card_exp_year'  => $card->expireYear,
            'card_cvv'      => $card->cvv,
            'customer_name' => $customer?->firstName ?? '',
            'customer_surname' => $customer?->lastName ?? '',
            'customer_email'   => $customer?->email ?? '',
            'customer_ip'      => $customer?->ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'hash'          => $this->generateHash(
                $request->getOrderId(),
                (string) MoneyFormatter::toPenny($request->getAmount()),
                $request->getCurrency(),
            ),
        ];

        // Sepet kalemleri
        $products = [];
        foreach ($request->getCartItems() as $item) {
            $products[] = [
                'id'       => $item->id,
                'name'     => $item->name,
                'category' => $item->category,
                'price'    => MoneyFormatter::toPenny($item->price),
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
                transactionId: $data['transaction_id'] ?? '',
                orderId: $data['order_id'] ?? $request->getOrderId(),
                amount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return PaymentResponse::failed(
            errorCode: $data['code'] ?? 'UNKNOWN',
            errorMessage: $data['message'] ?? 'Paynet ödeme başarısız.',
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
            'merchant_id'    => $this->config->get('merchant_id'),
            'transaction_id' => $request->getTransactionId(),
            'order_id'       => $request->getOrderId(),
            'amount'         => MoneyFormatter::toPenny($request->getAmount()),
            'reason'         => $request->getReason(),
            'hash'           => $this->generateHash(
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
                transactionId: $data['transaction_id'] ?? $request->getTransactionId(),
                refundedAmount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return RefundResponse::failed(
            errorCode: $data['code'] ?? 'UNKNOWN',
            errorMessage: $data['message'] ?? 'Paynet iade başarısız.',
            rawResponse: $data,
        );
    }

    public function query(QueryRequest $request): QueryResponse
    {
        $body = [
            'merchant_id'    => $this->config->get('merchant_id'),
            'transaction_id' => $request->getTransactionId(),
            'order_id'       => $request->getOrderId(),
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
                'declined', 'error'    => PaymentStatus::Failed,
                'pending'              => PaymentStatus::Pending,
                'refunded'             => PaymentStatus::Refunded,
                'cancelled'            => PaymentStatus::Cancelled,
                default                => PaymentStatus::Pending,
            };

            return QueryResponse::successful(
                transactionId: $data['transaction_id'] ?? '',
                orderId: $data['order_id'] ?? '',
                amount: MoneyFormatter::toFloat((int) ($data['amount'] ?? 0)),
                status: $status,
                rawResponse: $data,
            );
        }

        return QueryResponse::failed(
            errorCode: $data['code'] ?? 'UNKNOWN',
            errorMessage: $data['message'] ?? 'Paynet sorgu başarısız.',
            rawResponse: $data,
        );
    }

    public function initSecurePayment(SecurePaymentRequest $request): SecureInitResponse
    {
        $card = $request->getCard();
        if ($card === null) {
            return SecureInitResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $customer = $request->getCustomer();

        $body = [
            'merchant_id'   => $this->config->get('merchant_id'),
            'amount'        => MoneyFormatter::toPenny($request->getAmount()),
            'currency'      => $request->getCurrency(),
            'order_id'      => $request->getOrderId(),
            'description'   => $request->getDescription(),
            'installment'   => $request->getInstallmentCount(),
            'card_holder'   => $card->cardHolderName,
            'card_no'       => $card->cardNumber,
            'card_exp_month' => $card->expireMonth,
            'card_exp_year'  => $card->expireYear,
            'card_cvv'      => $card->cvv,
            'customer_name' => $customer?->firstName ?? '',
            'customer_email' => $customer?->email ?? '',
            'customer_ip'    => $customer?->ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'success_url'   => $request->getSuccessUrl() ?: $request->getCallbackUrl(),
            'fail_url'      => $request->getFailUrl() ?: $request->getCallbackUrl(),
            'hash'          => $this->generateHash(
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
            return SecureInitResponse::html($data['html_content'], $data);
        }

        if (isset($data['redirect_url'])) {
            return SecureInitResponse::redirect($data['redirect_url'], [], $data);
        }

        return SecureInitResponse::failed(
            errorCode: $data['code'] ?? 'UNKNOWN',
            errorMessage: $data['message'] ?? 'Paynet 3D Secure başlatma başarısız.',
            rawResponse: $data,
        );
    }

    public function completeSecurePayment(SecureCallbackData $data): PaymentResponse
    {
        $mdStatus = $data->get('md_status', $data->get('mdStatus', ''));

        if ($mdStatus === '1') {
            $body = [
                'merchant_id'    => $this->config->get('merchant_id'),
                'payment_token'  => $data->get('payment_token', $data->get('token', '')),
                'order_id'       => $data->get('order_id', ''),
            ];

            $response = $this->httpClient->post(
                $this->getActiveBaseUrl() . '/payment/3d/complete',
                $this->buildHeaders(),
                json_encode($body, JSON_THROW_ON_ERROR),
            );
            $responseData = $response->toArray();

            if (($responseData['is_successful'] ?? false) === true) {
                return PaymentResponse::successful(
                    transactionId: $responseData['transaction_id'] ?? '',
                    orderId: $responseData['order_id'] ?? $data->get('order_id', ''),
                    amount: MoneyFormatter::toFloat((int) ($responseData['amount'] ?? 0)),
                    rawResponse: $responseData,
                );
            }

            return PaymentResponse::failed(
                errorCode: $responseData['code'] ?? 'UNKNOWN',
                errorMessage: $responseData['message'] ?? 'Paynet 3D Secure ödeme tamamlama başarısız.',
                rawResponse: $responseData,
            );
        }

        return PaymentResponse::failed(
            errorCode: (string) $data->get('code', 'UNKNOWN'),
            errorMessage: (string) $data->get('message', 'Paynet 3D Secure doğrulama başarısız.'),
            rawResponse: $data->toArray(),
        );
    }

    public function createSubscription(SubscriptionRequest $request): SubscriptionResponse
    {
        $card = $request->getCard();
        if ($card === null) {
            return SubscriptionResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $customer = $request->getCustomer();

        $body = [
            'merchant_id'   => $this->config->get('merchant_id'),
            'plan_name'     => $request->getPlanName(),
            'amount'        => MoneyFormatter::toPenny($request->getAmount()),
            'currency'      => $request->getCurrency(),
            'period'        => $request->getPeriod(),
            'period_interval' => $request->getPeriodInterval(),
            'card_holder'   => $card->cardHolderName,
            'card_no'       => $card->cardNumber,
            'card_exp_month' => $card->expireMonth,
            'card_exp_year'  => $card->expireYear,
            'card_cvv'      => $card->cvv,
            'customer_name' => $customer?->firstName ?? '',
            'customer_surname' => $customer?->lastName ?? '',
            'customer_email'   => $customer?->email ?? '',
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/subscription/create',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['is_successful'] ?? false) === true) {
            return SubscriptionResponse::successful(
                subscriptionId: $data['subscription_id'] ?? '',
                rawResponse: $data,
            );
        }

        return SubscriptionResponse::failed(
            errorCode: $data['code'] ?? 'UNKNOWN',
            errorMessage: $data['message'] ?? 'Paynet abonelik oluşturma başarısız.',
            rawResponse: $data,
        );
    }

    public function cancelSubscription(string $subscriptionId): SubscriptionResponse
    {
        $body = [
            'merchant_id'    => $this->config->get('merchant_id'),
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
            errorCode: $data['code'] ?? 'UNKNOWN',
            errorMessage: $data['message'] ?? 'Paynet abonelik iptali başarısız.',
            rawResponse: $data,
        );
    }

    public function queryInstallments(string $binNumber, float $amount): array
    {
        $body = [
            'merchant_id' => $this->config->get('merchant_id'),
            'bin'         => $binNumber,
            'amount'      => MoneyFormatter::toPenny($amount),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/installment-query',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        $installments = [];
        foreach ($data['installment_list'] ?? [] as $inst) {
            $count = (int) ($inst['count'] ?? 0);
            if ($count > 0) {
                $installments[] = InstallmentInfo::create(
                    count: $count,
                    perInstallment: MoneyFormatter::toFloat((int) ($inst['per_amount'] ?? 0)),
                    total: MoneyFormatter::toFloat((int) ($inst['total_amount'] ?? 0)),
                    interestRate: (float) ($inst['interest_rate'] ?? 0),
                );
            }
        }

        return $installments;
    }
}

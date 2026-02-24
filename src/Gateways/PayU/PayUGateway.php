<?php

declare(strict_types=1);

namespace Arpay\Gateways\PayU;

use Arpay\Contracts\PayableInterface;
use Arpay\Contracts\QueryableInterface;
use Arpay\Contracts\RefundableInterface;
use Arpay\Contracts\SecurePayableInterface;
use Arpay\Contracts\SubscribableInterface;
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
 * PayU Türkiye ödeme altyapısı gateway implementasyonu.
 *
 * PayU, uluslararası bir ödeme altyapısının Türkiye operasyonudur.
 * Taksit sorgulama desteği bulunmamaktadır.
 *
 * Yapılandırma:
 * ```php
 * $gateway = Arpay::create('payu', [
 *     'merchant'   => 'XXXXX',
 *     'secret_key' => 'YYYYY',
 * ]);
 * ```
 *
 * @author Armağan Gökce
 */
class PayUGateway extends AbstractGateway implements PayableInterface, RefundableInterface, QueryableInterface, SecurePayableInterface, SubscribableInterface
{
    private const LIVE_BASE_URL = 'https://secure.payu.com.tr';
    private const SANDBOX_BASE_URL = 'https://sandbox.payu.com.tr';

    public function getName(): string
    {
        return 'PayU';
    }

    public function getShortName(): string
    {
        return 'payu';
    }

    public function getSupportedFeatures(): array
    {
        return ['pay', 'payInstallment', 'refund', 'query', '3dsecure', 'subscription'];
    }

    public function pay(PaymentRequest $request): PaymentResponse
    {
        $card = $request->getCard();
        if (null === $card) {
            return PaymentResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $customer = $request->getCustomer();
        $billingAddress = $request->getBillingAddress();
        $amount = MoneyFormatter::toDecimalString($request->getAmount());

        $body = [
            'MERCHANT' => $this->config->get('merchant'),
            'ORDER_REF' => $request->getOrderId(),
            'ORDER_DATE' => gmdate('Y-m-d H:i:s'),
            'ORDER_PNAME[]' => $request->getDescription() ?: 'Ödeme',
            'ORDER_PCODE[]' => $request->getOrderId(),
            'ORDER_PPRICE[]' => $amount,
            'ORDER_PQTY[]' => '1',
            'ORDER_PRICE_TYPE[]' => 'GROSS',
            'PRICES_CURRENCY' => $request->getCurrency(),
            'PAY_METHOD' => 'CCVISAMC',
            'CC_NUMBER' => $card->cardNumber,
            'EXP_MONTH' => $card->expireMonth,
            'EXP_YEAR' => $card->expireYear,
            'CC_CVV' => $card->cvv,
            'CC_OWNER' => $card->cardHolderName,
            'SELECTED_INSTALLMENTS_NUMBER' => (string) $request->getInstallmentCount(),
            'CLIENT_IP' => $customer?->ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'BILL_FNAME' => $customer?->firstName ?? '',
            'BILL_LNAME' => $customer?->lastName ?? '',
            'BILL_EMAIL' => $customer?->email ?? '',
            'BILL_PHONE' => $customer?->phone ?? '',
            'BILL_ADDRESS' => $billingAddress?->address ?? '',
            'BILL_CITY' => $billingAddress?->city ?? '',
            'BILL_COUNTRYCODE' => $billingAddress?->country ?? 'TR',
            'ORDER_HASH' => $this->generateSignature(
                $request->getOrderId(),
                $amount,
                $request->getCurrency(),
            ),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/order/alu/v3',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['STATUS'] ?? '') === 'SUCCESS' || ($data['RETURN_CODE'] ?? '') === 'AUTHORIZED') {
            return PaymentResponse::successful(
                transactionId: $this->toString($data['REFNO'] ?? ''),
                orderId: $this->toString($data['ORDER_REF'] ?? $request->getOrderId()),
                amount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return PaymentResponse::failed(
            errorCode: $this->toString($data['RETURN_CODE'] ?? 'UNKNOWN'),
            errorMessage: $this->toString($data['RETURN_MESSAGE'] ?? 'PayU ödeme başarısız.'),
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
            'MERCHANT' => $this->config->get('merchant'),
            'ORDER_REF' => $request->getOrderId(),
            'ORDER_AMOUNT' => MoneyFormatter::toDecimalString($request->getAmount()),
            'ORDER_CURRENCY' => 'TRY',
            'IRN_DATE' => gmdate('Y-m-d H:i:s'),
            'AMOUNT' => MoneyFormatter::toDecimalString($request->getAmount()),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/order/irn',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['RESPONSE_CODE'] ?? '') === '0' || ($data['STATUS'] ?? '') === 'SUCCESS') {
            return RefundResponse::successful(
                transactionId: $this->toString($data['IRN_REFNO'] ?? $request->getTransactionId()),
                refundedAmount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return RefundResponse::failed(
            errorCode: $this->toString($data['RESPONSE_CODE'] ?? 'UNKNOWN'),
            errorMessage: $this->toString($data['RESPONSE_MSG'] ?? 'PayU iade başarısız.'),
            rawResponse: $data,
        );
    }

    public function query(QueryRequest $request): QueryResponse
    {
        $body = [
            'MERCHANT' => $this->config->get('merchant'),
            'ORDER_REF' => $request->getOrderId() ?: $request->getTransactionId(),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/order/ios',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        $orderStatus = $data['ORDER_STATUS'] ?? '';
        $status = match ($orderStatus) {
            'PAYMENT_AUTHORIZED', 'COMPLETE' => PaymentStatus::Successful,
            'PAYMENT_RECEIVED', 'IN_PROGRESS' => PaymentStatus::Pending,
            'REVERSED', 'REFUND' => PaymentStatus::Refunded,
            'CANCELED' => PaymentStatus::Cancelled,
            default => PaymentStatus::Failed,
        };

        if (!empty($data['ORDER_REF'])) {
            return QueryResponse::successful(
                transactionId: $this->toString($data['REFNO'] ?? ''),
                orderId: $this->toString($data['ORDER_REF']),
                amount: $this->toFloat($data['ORDER_AMOUNT'] ?? 0),
                status: $status,
                rawResponse: $data,
            );
        }

        return QueryResponse::failed(
            errorCode: $this->toString($data['RESPONSE_CODE'] ?? 'UNKNOWN'),
            errorMessage: $this->toString($data['RESPONSE_MSG'] ?? 'PayU sorgu başarısız.'),
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
        $amount = MoneyFormatter::toDecimalString($request->getAmount());

        $body = [
            'MERCHANT' => $this->config->get('merchant'),
            'ORDER_REF' => $request->getOrderId(),
            'ORDER_DATE' => gmdate('Y-m-d H:i:s'),
            'ORDER_PNAME[]' => $request->getDescription() ?: 'Ödeme',
            'ORDER_PCODE[]' => $request->getOrderId(),
            'ORDER_PPRICE[]' => $amount,
            'ORDER_PQTY[]' => '1',
            'PRICES_CURRENCY' => $request->getCurrency(),
            'PAY_METHOD' => 'CCVISAMC',
            'CC_NUMBER' => $card->cardNumber,
            'EXP_MONTH' => $card->expireMonth,
            'EXP_YEAR' => $card->expireYear,
            'CC_CVV' => $card->cvv,
            'CC_OWNER' => $card->cardHolderName,
            'SELECTED_INSTALLMENTS_NUMBER' => (string) $request->getInstallmentCount(),
            '3DS_ENROLLED' => 'YES',
            'BACK_REF' => $request->getSuccessUrl() ?: $request->getCallbackUrl(),
            'CLIENT_IP' => $customer?->ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'BILL_FNAME' => $customer?->firstName ?? '',
            'BILL_LNAME' => $customer?->lastName ?? '',
            'BILL_EMAIL' => $customer?->email ?? '',
            'ORDER_HASH' => $this->generateSignature(
                $request->getOrderId(),
                $amount,
                $request->getCurrency(),
            ),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/order/alu/v3',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (isset($data['URL_3DS'])) {
            return SecureInitResponse::redirect($this->toString($data['URL_3DS']), [], $data);
        }

        if (isset($data['3DS_HTML'])) {
            return SecureInitResponse::html($this->toString($data['3DS_HTML']), $data);
        }

        return SecureInitResponse::failed(
            errorCode: $this->toString($data['RETURN_CODE'] ?? 'UNKNOWN'),
            errorMessage: $this->toString($data['RETURN_MESSAGE'] ?? 'PayU 3D Secure başlatma başarısız.'),
            rawResponse: $data,
        );
    }

    public function completeSecurePayment(SecureCallbackData $data): PaymentResponse
    {
        $status = $data->get('STATUS', $data->get('status', ''));

        if ('SUCCESS' === $status || 'AUTHORIZED' === $status) {
            return PaymentResponse::successful(
                transactionId: $this->toString($data->get('REFNO', $data->get('refno', ''))),
                orderId: $this->toString($data->get('ORDER_REF', $data->get('order_ref', ''))),
                amount: $this->toFloat($data->get('ORDER_AMOUNT', $data->get('amount', 0))),
                rawResponse: $data->toArray(),
            );
        }

        return PaymentResponse::failed(
            errorCode: $this->toString($data->get('RETURN_CODE', 'UNKNOWN')),
            errorMessage: $this->toString($data->get('RETURN_MESSAGE', 'PayU 3D Secure ödeme başarısız.')),
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
            'MERCHANT' => $this->config->get('merchant'),
            'REF_NO' => uniqid('SUB_', true),
            'PLAN_NAME' => $request->getPlanName(),
            'AMOUNT' => MoneyFormatter::toDecimalString($request->getAmount()),
            'CURRENCY' => $request->getCurrency(),
            'PERIOD' => $request->getPeriod(),
            'PERIOD_INTERVAL' => (string) $request->getPeriodInterval(),
            'CC_NUMBER' => $card->cardNumber,
            'EXP_MONTH' => $card->expireMonth,
            'EXP_YEAR' => $card->expireYear,
            'CC_CVV' => $card->cvv,
            'CC_OWNER' => $card->cardHolderName,
            'SUBSCRIBER_FNAME' => $customer?->firstName ?? '',
            'SUBSCRIBER_LNAME' => $customer?->lastName ?? '',
            'SUBSCRIBER_EMAIL' => $customer?->email ?? '',
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/order/tokens/',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['STATUS'] ?? '') === 'SUCCESS') {
            return SubscriptionResponse::successful(
                subscriptionId: $this->toString($data['IPN_CC_TOKEN'] ?? $data['TOKEN'] ?? ''),
                rawResponse: $data,
            );
        }

        return SubscriptionResponse::failed(
            errorCode: $this->toString($data['RETURN_CODE'] ?? 'UNKNOWN'),
            errorMessage: $this->toString($data['RETURN_MESSAGE'] ?? 'PayU abonelik oluşturma başarısız.'),
            rawResponse: $data,
        );
    }

    public function cancelSubscription(string $subscriptionId): SubscriptionResponse
    {
        $body = [
            'MERCHANT' => $this->config->get('merchant'),
            'TOKEN' => $subscriptionId,
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/order/tokens/cancel/',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['STATUS'] ?? '') === 'SUCCESS') {
            return SubscriptionResponse::successful($subscriptionId, 'cancelled', $data);
        }

        return SubscriptionResponse::failed(
            errorCode: $this->toString($data['RETURN_CODE'] ?? 'UNKNOWN'),
            errorMessage: $this->toString($data['RETURN_MESSAGE'] ?? 'PayU abonelik iptali başarısız.'),
            rawResponse: $data,
        );
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['merchant', 'secret_key'];
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
     * PayU HMAC imzası oluşturur.
     *
     * PayU imza formatı: md5(MERCHANT + order_ref + amount + currency + SECRET_KEY)
     */
    private function generateSignature(string $orderRef, string $amount, string $currency): string
    {
        $merchant = $this->toString($this->config->get('merchant'));
        $secretKey = $this->toString($this->config->get('secret_key'));
        $hashString = strlen($merchant) . $merchant
            . strlen($orderRef) . $orderRef
            . strlen($amount) . $amount
            . strlen($currency) . $currency;

        return HashGenerator::hmacSha256($hashString, $secretKey);
    }

    /**
     * PayU API istekleri için standart başlıkları oluşturur.
     *
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    private function toString(mixed $value, string $default = ''): string
    {
        return is_string($value) ? $value : $default;
    }

    private function toFloat(mixed $value, float $default = 0.0): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }
}

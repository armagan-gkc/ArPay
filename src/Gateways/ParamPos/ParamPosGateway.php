<?php

declare(strict_types=1);

namespace Arpay\Gateways\ParamPos;

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
use Arpay\Support\MoneyFormatter;

/**
 * ParamPos ödeme altyapısı gateway implementasyonu.
 *
 * ParamPos, SOAP'tan REST'e geçiş sürecinde olan bir Türk sanal POS altyapısıdır.
 * Bu implementasyon REST API endpoint'lerini kullanır.
 *
 * Yapılandırma:
 * ```php
 * $gateway = Arpay::create('parampos', [
 *     'client_code'     => 'XXXXX',
 *     'client_username' => 'user',
 *     'client_password' => 'pass',
 *     'guid'            => 'YYYYY-ZZZZZ-...',
 * ]);
 * ```
 *
 * @author Armağan Gökce
 */
class ParamPosGateway extends AbstractGateway implements PayableInterface, RefundableInterface, QueryableInterface, SecurePayableInterface, SubscribableInterface, InstallmentQueryableInterface
{
    private const LIVE_BASE_URL = 'https://pos.param.com.tr/rest';
    private const SANDBOX_BASE_URL = 'https://test-pos.param.com.tr/rest';

    public function getName(): string
    {
        return 'ParamPos';
    }

    public function getShortName(): string
    {
        return 'parampos';
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

        $body = [
            'CLIENT_CODE' => $this->config->get('client_code'),
            'CLIENT_USERNAME' => $this->config->get('client_username'),
            'CLIENT_PASSWORD' => $this->config->get('client_password'),
            'GUID' => $this->config->get('guid'),
            'KK_Sahibi' => $card->cardHolderName,
            'KK_No' => $card->cardNumber,
            'KK_SK_Ay' => $card->expireMonth,
            'KK_SK_Yil' => $card->expireYear,
            'KK_CVC' => $card->cvv,
            'Tutar' => MoneyFormatter::toDecimalString($request->getAmount()),
            'Doviz_Kodu' => $this->mapCurrency($request->getCurrency()),
            'Siparis_ID' => $request->getOrderId(),
            'Taksit' => (string) $request->getInstallmentCount(),
            'Islem_Tutar' => MoneyFormatter::toDecimalString($request->getAmount()),
            'IPAdr' => $request->getCustomer()?->ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/non3d',
            ['Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['Sonuc'] ?? '') === '1' || ($data['result_code'] ?? '') === '00') {
            return PaymentResponse::successful(
                transactionId: $this->toString($data['Dekont_ID'] ?? $data['transaction_id'] ?? ''),
                orderId: $request->getOrderId(),
                amount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return PaymentResponse::failed(
            errorCode: $this->toString($data['Sonuc_Str'] ?? $data['error_code'] ?? null, 'UNKNOWN'),
            errorMessage: $this->toString($data['Sonuc_Ack'] ?? $data['error_message'] ?? null, 'ParamPos ödeme başarısız.'),
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
            'CLIENT_CODE' => $this->config->get('client_code'),
            'CLIENT_USERNAME' => $this->config->get('client_username'),
            'CLIENT_PASSWORD' => $this->config->get('client_password'),
            'GUID' => $this->config->get('guid'),
            'Dekont_ID' => $request->getTransactionId() ?: $request->getOrderId(),
            'Tutar' => MoneyFormatter::toDecimalString($request->getAmount()),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/refund',
            ['Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['Sonuc'] ?? '') === '1') {
            return RefundResponse::successful(
                transactionId: $this->toString($data['Dekont_ID'] ?? null, $request->getTransactionId()),
                refundedAmount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return RefundResponse::failed(
            errorCode: $this->toString($data['Sonuc_Str'] ?? null, 'UNKNOWN'),
            errorMessage: $this->toString($data['Sonuc_Ack'] ?? null, 'ParamPos iade başarısız.'),
            rawResponse: $data,
        );
    }

    public function query(QueryRequest $request): QueryResponse
    {
        $body = [
            'CLIENT_CODE' => $this->config->get('client_code'),
            'CLIENT_USERNAME' => $this->config->get('client_username'),
            'CLIENT_PASSWORD' => $this->config->get('client_password'),
            'GUID' => $this->config->get('guid'),
            'Dekont_ID' => $request->getTransactionId() ?: $request->getOrderId(),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/query',
            ['Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['Sonuc'] ?? '') === '1') {
            return QueryResponse::successful(
                transactionId: $this->toString($data['Dekont_ID'] ?? ''),
                orderId: $this->toString($data['Siparis_ID'] ?? ''),
                amount: $this->toFloat($data['Tutar'] ?? 0),
                status: PaymentStatus::Successful,
                rawResponse: $data,
            );
        }

        return QueryResponse::failed(
            errorCode: $this->toString($data['Sonuc_Str'] ?? null, 'UNKNOWN'),
            errorMessage: $this->toString($data['Sonuc_Ack'] ?? null, 'ParamPos sorgu başarısız.'),
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
            'CLIENT_CODE' => $this->config->get('client_code'),
            'CLIENT_USERNAME' => $this->config->get('client_username'),
            'CLIENT_PASSWORD' => $this->config->get('client_password'),
            'GUID' => $this->config->get('guid'),
            'KK_Sahibi' => $card->cardHolderName,
            'KK_No' => $card->cardNumber,
            'KK_SK_Ay' => $card->expireMonth,
            'KK_SK_Yil' => $card->expireYear,
            'KK_CVC' => $card->cvv,
            'Tutar' => MoneyFormatter::toDecimalString($request->getAmount()),
            'Doviz_Kodu' => $this->mapCurrency($request->getCurrency()),
            'Siparis_ID' => $request->getOrderId(),
            'Taksit' => (string) $request->getInstallmentCount(),
            'Basarili_URL' => $request->getSuccessUrl() ?: $request->getCallbackUrl(),
            'Hata_URL' => $request->getFailUrl() ?: $request->getCallbackUrl(),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/3d',
            ['Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (isset($data['UCD_HTML'])) {
            return SecureInitResponse::html($this->toString($data['UCD_HTML']), $data);
        }

        if (isset($data['redirect_url'])) {
            return SecureInitResponse::redirect($this->toString($data['redirect_url']), [], $data);
        }

        return SecureInitResponse::failed(
            errorCode: $this->toString($data['Sonuc_Str'] ?? null, 'UNKNOWN'),
            errorMessage: $this->toString($data['Sonuc_Ack'] ?? null, 'ParamPos 3D Secure başlatma başarısız.'),
            rawResponse: $data,
        );
    }

    public function completeSecurePayment(SecureCallbackData $data): PaymentResponse
    {
        $sonuc = $data->get('Sonuc', $data->get('mdStatus', ''));

        if ('1' === $sonuc || 1 === $sonuc) {
            return PaymentResponse::successful(
                transactionId: $this->toString($data->get('Dekont_ID', $data->get('transaction_id', ''))),
                orderId: $this->toString($data->get('Siparis_ID', $data->get('order_id', ''))),
                amount: $this->toFloat($data->get('Tutar', $data->get('amount', 0))),
                rawResponse: $data->toArray(),
            );
        }

        return PaymentResponse::failed(
            errorCode: $this->toString($data->get('Sonuc_Str', $data->get('error_code', 'UNKNOWN'))),
            errorMessage: $this->toString($data->get('Sonuc_Ack', $data->get('error_message', 'ParamPos 3D Secure ödeme başarısız.'))),
            rawResponse: $data->toArray(),
        );
    }

    public function createSubscription(SubscriptionRequest $request): SubscriptionResponse
    {
        $card = $request->getCard();
        if (null === $card) {
            return SubscriptionResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $body = [
            'CLIENT_CODE' => $this->config->get('client_code'),
            'CLIENT_USERNAME' => $this->config->get('client_username'),
            'CLIENT_PASSWORD' => $this->config->get('client_password'),
            'GUID' => $this->config->get('guid'),
            'Plan_Adi' => $request->getPlanName(),
            'Tutar' => MoneyFormatter::toDecimalString($request->getAmount()),
            'Periyot' => $request->getPeriod(),
            'KK_No' => $card->cardNumber,
            'KK_SK_Ay' => $card->expireMonth,
            'KK_SK_Yil' => $card->expireYear,
            'KK_CVC' => $card->cvv,
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/subscription/create',
            ['Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['Sonuc'] ?? '') === '1') {
            return SubscriptionResponse::successful(
                subscriptionId: $this->toString($data['subscription_id'] ?? ''),
                rawResponse: $data,
            );
        }

        return SubscriptionResponse::failed(
            errorCode: $this->toString($data['Sonuc_Str'] ?? null, 'UNKNOWN'),
            errorMessage: $this->toString($data['Sonuc_Ack'] ?? null, 'ParamPos abonelik oluşturma başarısız.'),
            rawResponse: $data,
        );
    }

    public function cancelSubscription(string $subscriptionId): SubscriptionResponse
    {
        $body = [
            'CLIENT_CODE' => $this->config->get('client_code'),
            'CLIENT_USERNAME' => $this->config->get('client_username'),
            'CLIENT_PASSWORD' => $this->config->get('client_password'),
            'GUID' => $this->config->get('guid'),
            'subscription_id' => $subscriptionId,
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/subscription/cancel',
            ['Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['Sonuc'] ?? '') === '1') {
            return SubscriptionResponse::successful($subscriptionId, 'cancelled', $data);
        }

        return SubscriptionResponse::failed(
            errorCode: $this->toString($data['Sonuc_Str'] ?? null, 'UNKNOWN'),
            errorMessage: $this->toString($data['Sonuc_Ack'] ?? null, 'ParamPos abonelik iptali başarısız.'),
            rawResponse: $data,
        );
    }

    public function queryInstallments(string $binNumber, float $amount): array
    {
        $body = [
            'CLIENT_CODE' => $this->config->get('client_code'),
            'CLIENT_USERNAME' => $this->config->get('client_username'),
            'CLIENT_PASSWORD' => $this->config->get('client_password'),
            'GUID' => $this->config->get('guid'),
            'BIN' => $binNumber,
            'Tutar' => MoneyFormatter::toDecimalString($amount),
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/installment-query',
            ['Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        $installments = [];
        $rawInstallments = $data['installments'] ?? [];
        if (is_array($rawInstallments)) {
            foreach ($rawInstallments as $inst) {
                if (!is_array($inst)) {
                    continue;
                }
                $count = $this->toInt($inst['count'] ?? 0);
                if ($count > 0) {
                    $installments[] = InstallmentInfo::create(
                        count: $count,
                        perInstallment: $this->toFloat($inst['per_amount'] ?? 0),
                        total: $this->toFloat($inst['total_amount'] ?? 0),
                        interestRate: $this->toFloat($inst['interest_rate'] ?? 0),
                    );
                }
            }
        }

        return $installments;
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['client_code', 'client_username', 'client_password', 'guid'];
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
     * Para birimi kodunu ParamPos formatına dönüştürür.
     */
    private function mapCurrency(string $currency): string
    {
        return match ($currency) {
            'TRY' => '1008',
            'USD' => '1',
            'EUR' => '2',
            'GBP' => '3',
            default => '1008',
        };
    }

    private function toString(mixed $value, string $default = ''): string
    {
        return is_string($value) ? $value : $default;
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

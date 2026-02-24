<?php

declare(strict_types=1);

namespace Arpay\Gateways\PayTR;

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
use Arpay\Exceptions\AuthenticationException;
use Arpay\Gateways\AbstractGateway;
use Arpay\Support\HashGenerator;

/**
 * PayTR ödeme altyapısı gateway implementasyonu.
 *
 * PayTR, Türkiye'nin en yaygın kullanılan sanal POS altyapılarından biridir.
 * HMAC-SHA256 tabanlı token imzalama ve iframe/direct API yöntemlerini destekler.
 *
 * Yapılandırma:
 * ```php
 * $gateway = Arpay::create('paytr', [
 *     'merchant_id'   => '123456',
 *     'merchant_key'  => 'XXXXXXXXXXXXXXXX',
 *     'merchant_salt' => 'YYYYYYYYYYYYYYYY',
 *     'test_mode'     => true,
 * ]);
 * ```
 *
 * @author Armağan Gökce
 *
 * @see https://dev.paytr.com/
 */
class PayTRGateway extends AbstractGateway implements PayableInterface, RefundableInterface, QueryableInterface, SecurePayableInterface, SubscribableInterface, InstallmentQueryableInterface
{
    /** @var string PayTR Direct API ödeme path'i */
    private const DIRECT_API_PATH = '/odeme/api/get-token';

    /** @var string PayTR iframe token path'i */
    private const IFRAME_TOKEN_PATH = '/odeme/api/get-token';

    /** @var string PayTR iade path'i */
    private const REFUND_PATH = '/odeme/iade';

    /** @var string PayTR BIN sorgulama path'i */
    private const BIN_QUERY_PATH = '/odeme/api/bin-detail';

    public function getName(): string
    {
        return 'PayTR';
    }

    public function getShortName(): string
    {
        return 'paytr';
    }

    public function getSupportedFeatures(): array
    {
        return ['pay', 'payInstallment', 'refund', 'query', '3dsecure', 'subscription', 'installmentQuery'];
    }

    /**
     * Tek çekim ödeme yapar.
     *
     * PayTR Direct API üzerinden kart bilgileriyle
     * doğrudan (non-3D) ödeme gerçekleştirir.
     *
     * {@inheritdoc}
     */
    public function pay(PaymentRequest $request): PaymentResponse
    {
        $card = $request->getCard();
        if (null === $card) {
            return PaymentResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        // Sepet ürünlerini PayTR formatına dönüştür
        $basketItems = [];
        foreach ($request->getCartItems() as $item) {
            $basketItems[] = [
                'name' => $item->name,
                'price' => $item->price,
                'quantity' => $item->quantity,
            ];
        }

        // Sepet boşsa ödeme açıklamasından oluştur
        if (empty($basketItems)) {
            $basketItems[] = [
                'name' => $request->getDescription() ?: 'Ödeme',
                'price' => $request->getAmount(),
                'quantity' => 1,
            ];
        }

        $params = [
            'merchant_id' => $this->config->get('merchant_id'),
            'user_ip' => $request->getCustomer()?->ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'merchant_oid' => $request->getOrderId(),
            'email' => $request->getCustomer()?->email ?? 'musteri@example.com',
            'payment_amount' => PayTRHelper::formatAmount($request->getAmount()),
            'user_basket' => PayTRHelper::formatBasket($basketItems),
            'no_installment' => $request->getInstallmentCount() <= 1 ? '1' : '0',
            'max_installment' => (string) $request->getInstallmentCount(),
            'currency' => 'TRY' === $request->getCurrency() ? 'TL' : $request->getCurrency(),
            'test_mode' => $this->testMode ? '1' : '0',
            'cc_owner' => $card->cardHolderName,
            'card_number' => $card->cardNumber,
            'expiry_month' => $card->expireMonth,
            'expiry_year' => $card->expireYear,
            'cvv' => $card->cvv,
            'non_3d' => '1',
            'installment_count' => (string) $request->getInstallmentCount(),
        ];

        // Token oluştur
        $params['paytr_token'] = PayTRHelper::generateToken($params, $this->config);

        // API isteği gönder
        $response = $this->httpClient->post($this->getActiveBaseUrl() . self::DIRECT_API_PATH, [], $params);
        $data = $response->toArray();

        // Yanıtı değerlendir
        if (($data['status'] ?? '') === 'success') {
            return PaymentResponse::successful(
                transactionId: $data['trans_id'] ?? $data['merchant_oid'] ?? $request->getOrderId(),
                orderId: $request->getOrderId(),
                amount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return PaymentResponse::failed(
            errorCode: (string) ($data['err_no'] ?? 'UNKNOWN'),
            errorMessage: $data['err_msg'] ?? 'PayTR ödeme başarısız.',
            rawResponse: $data,
        );
    }

    /**
     * Taksitli ödeme yapar.
     *
     * PayTR'da taksitli ödeme tek çekim ile aynı endpoint'i kullanır,
     * fark installmentCount parametresindedir.
     *
     * {@inheritdoc}
     */
    public function payInstallment(PaymentRequest $request): PaymentResponse
    {
        // Taksit sayısı en az 2 olmalı
        if ($request->getInstallmentCount() < 2) {
            return PaymentResponse::failed('INVALID_INSTALLMENT', 'Taksitli ödeme için en az 2 taksit gereklidir.');
        }

        return $this->pay($request);
    }

    /**
     * İade işlemi yapar.
     *
     * {@inheritdoc}
     */
    public function refund(RefundRequest $request): RefundResponse
    {
        $amount = PayTRHelper::formatAmount($request->getAmount());
        $orderId = $request->getOrderId() ?: $request->getTransactionId();

        $params = [
            'merchant_id' => $this->config->get('merchant_id'),
            'merchant_oid' => $orderId,
            'return_amount' => $amount,
            'paytr_token' => PayTRHelper::generateRefundToken($orderId, $amount, $this->config),
        ];

        $response = $this->httpClient->post($this->getActiveBaseUrl() . self::REFUND_PATH, [], $params);
        $data = $response->toArray();

        if (($data['status'] ?? '') === 'success') {
            return RefundResponse::successful(
                transactionId: $orderId,
                refundedAmount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return RefundResponse::failed(
            errorCode: (string) ($data['err_no'] ?? 'UNKNOWN'),
            errorMessage: $data['err_msg'] ?? 'PayTR iade başarısız.',
            rawResponse: $data,
        );
    }

    /**
     * Ödeme durumunu sorgular.
     *
     * {@inheritdoc}
     */
    public function query(QueryRequest $request): QueryResponse
    {
        $orderId = $request->getOrderId() ?: $request->getTransactionId();

        $hashStr = $this->config->get('merchant_id') . $orderId . $this->config->get('merchant_salt');
        $token = HashGenerator::hmacSha256Base64($hashStr, $this->config->get('merchant_key'));

        $params = [
            'merchant_id' => $this->config->get('merchant_id'),
            'merchant_oid' => $orderId,
            'paytr_token' => $token,
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/odeme/durum-sorgu',
            [],
            $params,
        );
        $data = $response->toArray();

        if (($data['status'] ?? '') === 'success') {
            $paymentStatus = match ($data['payment_status'] ?? '') {
                'success' => PaymentStatus::Successful,
                'failed' => PaymentStatus::Failed,
                'pending' => PaymentStatus::Pending,
                default => PaymentStatus::Pending,
            };

            return QueryResponse::successful(
                transactionId: $orderId,
                orderId: $orderId,
                amount: isset($data['payment_amount']) ? (float) $data['payment_amount'] / 100 : 0.0,
                status: $paymentStatus,
                rawResponse: $data,
            );
        }

        return QueryResponse::failed(
            errorCode: (string) ($data['err_no'] ?? 'UNKNOWN'),
            errorMessage: $data['err_msg'] ?? 'PayTR sorgu başarısız.',
            rawResponse: $data,
        );
    }

    /**
     * 3D Secure ödeme başlatır.
     *
     * PayTR iframe token alarak 3D Secure akışını başlatır.
     * Dönen HTML içerik doğrudan echo edilebilir.
     *
     * {@inheritdoc}
     */
    public function initSecurePayment(SecurePaymentRequest $request): SecureInitResponse
    {
        $card = $request->getCard();
        if (null === $card) {
            return SecureInitResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $basketItems = [];
        foreach ($request->getCartItems() as $item) {
            $basketItems[] = [
                'name' => $item->name,
                'price' => $item->price,
                'quantity' => $item->quantity,
            ];
        }

        if (empty($basketItems)) {
            $basketItems[] = [
                'name' => $request->getDescription() ?: 'Ödeme',
                'price' => $request->getAmount(),
                'quantity' => 1,
            ];
        }

        $params = [
            'merchant_id' => $this->config->get('merchant_id'),
            'user_ip' => $request->getCustomer()?->ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'merchant_oid' => $request->getOrderId(),
            'email' => $request->getCustomer()?->email ?? 'musteri@example.com',
            'payment_amount' => PayTRHelper::formatAmount($request->getAmount()),
            'user_basket' => PayTRHelper::formatBasket($basketItems),
            'no_installment' => $request->getInstallmentCount() <= 1 ? '1' : '0',
            'max_installment' => (string) $request->getInstallmentCount(),
            'currency' => 'TRY' === $request->getCurrency() ? 'TL' : $request->getCurrency(),
            'test_mode' => $this->testMode ? '1' : '0',
            'merchant_ok_url' => $request->getSuccessUrl() ?: $request->getCallbackUrl(),
            'merchant_fail_url' => $request->getFailUrl() ?: $request->getCallbackUrl(),
            'cc_owner' => $card->cardHolderName,
            'card_number' => $card->cardNumber,
            'expiry_month' => $card->expireMonth,
            'expiry_year' => $card->expireYear,
            'cvv' => $card->cvv,
            'installment_count' => (string) $request->getInstallmentCount(),
        ];

        $params['paytr_token'] = PayTRHelper::generateToken($params, $this->config);

        $response = $this->httpClient->post($this->getActiveBaseUrl() . self::IFRAME_TOKEN_PATH, [], $params);
        $data = $response->toArray();

        if (($data['status'] ?? '') === 'success' && isset($data['token'])) {
            // PayTR iframe URL'sini oluştur
            $iframeUrl = "https://www.paytr.com/odeme/guvenli/{$data['token']}";

            $html = <<<HTML
            <!DOCTYPE html>
            <html lang="tr">
            <head><meta charset="UTF-8"><title>3D Secure Ödeme</title></head>
            <body style="margin:0;padding:0;">
                <iframe src="{$iframeUrl}" id="paytriframe" frameborder="0"
                    style="width:100%;height:100vh;border:0;"></iframe>
                <script>
                    iframe = document.getElementById('paytriframe');
                    iframe.style.width = '100%';
                    iframe.style.height = '100vh';
                </script>
            </body>
            </html>
            HTML;

            return SecureInitResponse::html($html, $data);
        }

        return SecureInitResponse::failed(
            errorCode: (string) ($data['err_no'] ?? 'UNKNOWN'),
            errorMessage: $data['err_msg'] ?? 'PayTR 3D Secure başlatma başarısız.',
            rawResponse: $data,
        );
    }

    /**
     * 3D Secure callback'ini işler ve ödemeyi tamamlar.
     *
     * PayTR callback'ten gelen POST verilerini doğrular.
     * Hash kontrolü başarısızsa AuthenticationException fırlatır.
     *
     * {@inheritdoc}
     *
     * @throws AuthenticationException Hash doğrulaması başarısızsa
     */
    public function completeSecurePayment(SecureCallbackData $data): PaymentResponse
    {
        $merchantOid = (string) $data->get('merchant_oid', '');
        $status = (string) $data->get('status', '');
        $totalAmount = (string) $data->get('total_amount', '');
        $hash = (string) $data->get('hash', '');

        // Hash doğrulaması
        $isValid = PayTRHelper::verifyCallback(
            merchantOid: $merchantOid,
            merchantSalt: $this->config->get('merchant_salt'),
            merchantKey: $this->config->get('merchant_key'),
            status: $status,
            totalAmount: $totalAmount,
            expectedHash: $hash,
        );

        if (!$isValid) {
            throw new AuthenticationException('PayTR callback hash doğrulaması başarısız.');
        }

        if ('success' === $status) {
            return PaymentResponse::successful(
                transactionId: $merchantOid,
                orderId: $merchantOid,
                amount: (float) $totalAmount / 100,
                rawResponse: $data->toArray(),
            );
        }

        return PaymentResponse::failed(
            errorCode: 'PAYMENT_FAILED',
            errorMessage: $data->get('failed_reason_msg', 'PayTR 3D Secure ödeme başarısız.'),
            rawResponse: $data->toArray(),
        );
    }

    /**
     * Abonelik / tekrarlayan ödeme oluşturur.
     *
     * {@inheritdoc}
     */
    public function createSubscription(SubscriptionRequest $request): SubscriptionResponse
    {
        /*
         * PayTR tekrarlayan ödeme desteğini link API veya
         * recurring parametreleri üzerinden sunar.
         * Burada temel bir implementasyon sağlanmıştır.
         */
        $card = $request->getCard();
        if (null === $card) {
            return SubscriptionResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $params = [
            'merchant_id' => $this->config->get('merchant_id'),
            'utoken' => 'aut', // Otomatik ödeme tokenı al
            'plan_name' => $request->getPlanName(),
            'amount' => PayTRHelper::formatAmount($request->getAmount()),
            'currency' => 'TRY' === $request->getCurrency() ? 'TL' : $request->getCurrency(),
            'period' => $request->getPeriod(),
            'cc_owner' => $card->cardHolderName,
            'card_number' => $card->cardNumber,
            'expiry_month' => $card->expireMonth,
            'expiry_year' => $card->expireYear,
            'cvv' => $card->cvv,
            'test_mode' => $this->testMode ? '1' : '0',
        ];

        $hashStr = $this->config->get('merchant_id') . $request->getPlanName() . $this->config->get('merchant_salt');
        $params['paytr_token'] = HashGenerator::hmacSha256Base64($hashStr, $this->config->get('merchant_key'));

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/odeme/api/recurring',
            [],
            $params,
        );
        $data = $response->toArray();

        if (($data['status'] ?? '') === 'success') {
            return SubscriptionResponse::successful(
                subscriptionId: $data['subscription_id'] ?? '',
                status: 'active',
                rawResponse: $data,
            );
        }

        return SubscriptionResponse::failed(
            errorCode: (string) ($data['err_no'] ?? 'UNKNOWN'),
            errorMessage: $data['err_msg'] ?? 'PayTR abonelik oluşturma başarısız.',
            rawResponse: $data,
        );
    }

    /**
     * Aboneliği iptal eder.
     *
     * {@inheritdoc}
     */
    public function cancelSubscription(string $subscriptionId): SubscriptionResponse
    {
        $hashStr = $this->config->get('merchant_id') . $subscriptionId . $this->config->get('merchant_salt');
        $token = HashGenerator::hmacSha256Base64($hashStr, $this->config->get('merchant_key'));

        $params = [
            'merchant_id' => $this->config->get('merchant_id'),
            'subscription_id' => $subscriptionId,
            'paytr_token' => $token,
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/odeme/api/recurring/cancel',
            [],
            $params,
        );
        $data = $response->toArray();

        if (($data['status'] ?? '') === 'success') {
            return SubscriptionResponse::successful(
                subscriptionId: $subscriptionId,
                status: 'cancelled',
                rawResponse: $data,
            );
        }

        return SubscriptionResponse::failed(
            errorCode: (string) ($data['err_no'] ?? 'UNKNOWN'),
            errorMessage: $data['err_msg'] ?? 'PayTR abonelik iptali başarısız.',
            rawResponse: $data,
        );
    }

    /**
     * Taksit seçeneklerini sorgular.
     *
     * {@inheritdoc}
     */
    public function queryInstallments(string $binNumber, float $amount): array
    {
        $hashStr = $this->config->get('merchant_id') . $binNumber . $this->config->get('merchant_salt');
        $token = HashGenerator::hmacSha256Base64($hashStr, $this->config->get('merchant_key'));

        $params = [
            'merchant_id' => $this->config->get('merchant_id'),
            'bin_number' => $binNumber,
            'paytr_token' => $token,
        ];

        $response = $this->httpClient->post($this->getActiveBaseUrl() . self::BIN_QUERY_PATH, [], $params);
        $data = $response->toArray();

        $installments = [];

        if (($data['status'] ?? '') === 'success' && isset($data['installments'])) {
            foreach ($data['installments'] as $inst) {
                $count = (int) ($inst['count'] ?? 0);
                $rate = (float) ($inst['rate'] ?? 0);
                $totalAmount = $amount * (1 + $rate / 100);
                $perInstallment = $totalAmount / max(1, $count);

                $installments[] = InstallmentInfo::create(
                    count: $count,
                    perInstallment: round($perInstallment, 2),
                    total: round($totalAmount, 2),
                    interestRate: $rate,
                );
            }
        }

        return $installments;
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['merchant_id', 'merchant_key', 'merchant_salt'];
    }

    protected function getBaseUrl(): string
    {
        return 'https://www.paytr.com';
    }

    protected function getTestBaseUrl(): string
    {
        return 'https://test.paytr.com';
    }
}

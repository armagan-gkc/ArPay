<?php

declare(strict_types=1);

namespace Arpay\Gateways\Iyzico;

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

/**
 * Iyzico ödeme altyapısı gateway implementasyonu.
 *
 * Iyzico, Türkiye'nin en popüler ödeme altyapılarından biridir.
 * REST tabanlı JSON API kullanır. Tüm ödeme özelliklerini destekler.
 *
 * Yapılandırma:
 * ```php
 * $gateway = Arpay::create('iyzico', [
 *     'api_key'    => 'sandbox-XXXXXXXXX',
 *     'secret_key' => 'sandbox-YYYYYYYYY',
 *     'base_url'   => 'https://sandbox-api.iyzipay.com', // opsiyonel
 * ]);
 * ```
 *
 * NOT: Iyzico sepet ürünü (en az 1 adet CartItem) zorunlu tutar.
 *
 * @author Armağan Gökce
 *
 * @see https://dev.iyzipay.com/
 */
class IyzicoGateway extends AbstractGateway implements PayableInterface, RefundableInterface, QueryableInterface, SecurePayableInterface, SubscribableInterface, InstallmentQueryableInterface
{
    /** @var string Canlı API URL'si */
    private const LIVE_BASE_URL = 'https://api.iyzipay.com';

    /** @var string Sandbox API URL'si */
    private const SANDBOX_BASE_URL = 'https://sandbox-api.iyzipay.com';

    public function getName(): string
    {
        return 'Iyzico';
    }

    public function getShortName(): string
    {
        return 'iyzico';
    }

    public function getSupportedFeatures(): array
    {
        return ['pay', 'payInstallment', 'refund', 'query', '3dsecure', 'subscription', 'installmentQuery'];
    }

    /**
     * Tek çekim ödeme yapar.
     *
     * Iyzico non-3D ödeme API'si üzerinden direkt ödeme alır.
     *
     * {@inheritdoc}
     */
    public function pay(PaymentRequest $request): PaymentResponse
    {
        $card = $request->getCard();
        if (null === $card) {
            return PaymentResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $body = $this->buildPaymentBody($request);
        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);

        $headers = IyzicoHelper::generateHeaders(
            $this->config->get('api_key'),
            $this->config->get('secret_key'),
            $jsonBody,
        );

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/auth',
            $headers,
            $jsonBody,
        );
        $data = $response->toArray();

        return $this->parsePaymentResponse($data, $request->getOrderId());
    }

    /**
     * Taksitli ödeme yapar.
     *
     * {@inheritdoc}
     */
    public function payInstallment(PaymentRequest $request): PaymentResponse
    {
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
        $body = [
            'locale' => 'tr',
            'paymentTransactionId' => $request->getTransactionId(),
            'price' => IyzicoHelper::formatAmount($request->getAmount()),
            'currency' => 'TRY',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ];

        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);

        $headers = IyzicoHelper::generateHeaders(
            $this->config->get('api_key'),
            $this->config->get('secret_key'),
            $jsonBody,
        );

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/refund',
            $headers,
            $jsonBody,
        );
        $data = $response->toArray();

        if (($data['status'] ?? '') === 'success') {
            return RefundResponse::successful(
                transactionId: $data['paymentId'] ?? $request->getTransactionId(),
                refundedAmount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return RefundResponse::failed(
            errorCode: (string) ($data['errorCode'] ?? 'UNKNOWN'),
            errorMessage: $data['errorMessage'] ?? 'Iyzico iade başarısız.',
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
        $body = [
            'locale' => 'tr',
            'paymentId' => $request->getTransactionId(),
        ];

        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);

        $headers = IyzicoHelper::generateHeaders(
            $this->config->get('api_key'),
            $this->config->get('secret_key'),
            $jsonBody,
        );

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/detail',
            $headers,
            $jsonBody,
        );
        $data = $response->toArray();

        if (($data['status'] ?? '') === 'success') {
            $paymentStatus = match ($data['paymentStatus'] ?? '') {
                'SUCCESS' => PaymentStatus::Successful,
                'FAILURE' => PaymentStatus::Failed,
                'INIT_THREEDS',
                'CALLBACK_THREEDS' => PaymentStatus::Pending,
                default => PaymentStatus::Pending,
            };

            return QueryResponse::successful(
                transactionId: $data['paymentId'] ?? '',
                orderId: $request->getOrderId(),
                amount: isset($data['price']) ? (float) $data['price'] : 0.0,
                status: $paymentStatus,
                rawResponse: $data,
            );
        }

        return QueryResponse::failed(
            errorCode: (string) ($data['errorCode'] ?? 'UNKNOWN'),
            errorMessage: $data['errorMessage'] ?? 'Iyzico sorgu başarısız.',
            rawResponse: $data,
        );
    }

    /**
     * 3D Secure ödeme başlatır.
     *
     * Iyzico 3D Secure initialize endpoint'ine istek gönderir.
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

        $body = $this->buildPaymentBody($request);
        $body['callbackUrl'] = $request->getCallbackUrl();

        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);

        $headers = IyzicoHelper::generateHeaders(
            $this->config->get('api_key'),
            $this->config->get('secret_key'),
            $jsonBody,
        );

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/3dsecure/initialize',
            $headers,
            $jsonBody,
        );
        $data = $response->toArray();

        if (($data['status'] ?? '') === 'success' && isset($data['threeDSHtmlContent'])) {
            // Iyzico Base64 kodlanmış HTML döndürür
            $htmlContent = base64_decode($data['threeDSHtmlContent'], true);

            return SecureInitResponse::html(
                false !== $htmlContent ? $htmlContent : '',
                $data,
            );
        }

        return SecureInitResponse::failed(
            errorCode: (string) ($data['errorCode'] ?? 'UNKNOWN'),
            errorMessage: $data['errorMessage'] ?? 'Iyzico 3D Secure başlatma başarısız.',
            rawResponse: $data,
        );
    }

    /**
     * 3D Secure callback'ini işler.
     *
     * {@inheritdoc}
     */
    public function completeSecurePayment(SecureCallbackData $data): PaymentResponse
    {
        $paymentId = (string) $data->get('paymentId', '');

        $body = [
            'locale' => 'tr',
            'paymentId' => $paymentId,
        ];

        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);

        $headers = IyzicoHelper::generateHeaders(
            $this->config->get('api_key'),
            $this->config->get('secret_key'),
            $jsonBody,
        );

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/3dsecure/auth',
            $headers,
            $jsonBody,
        );
        $responseData = $response->toArray();

        return $this->parsePaymentResponse($responseData, '');
    }

    /**
     * Abonelik oluşturur.
     *
     * {@inheritdoc}
     */
    public function createSubscription(SubscriptionRequest $request): SubscriptionResponse
    {
        $card = $request->getCard();
        if (null === $card) {
            return SubscriptionResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $body = [
            'locale' => 'tr',
            'pricingPlanReferenceCode' => $request->getPlanName(),
            'paymentCard' => [
                'cardHolderName' => $card->cardHolderName,
                'cardNumber' => $card->cardNumber,
                'expireMonth' => $card->expireMonth,
                'expireYear' => $card->expireYear,
                'cvc' => $card->cvv,
            ],
            'customer' => [
                'name' => $request->getCustomer()?->firstName ?? '',
                'surname' => $request->getCustomer()?->lastName ?? '',
                'email' => $request->getCustomer()?->email ?? '',
            ],
        ];

        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);

        $headers = IyzicoHelper::generateHeaders(
            $this->config->get('api_key'),
            $this->config->get('secret_key'),
            $jsonBody,
        );

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/v2/subscription/initialize',
            $headers,
            $jsonBody,
        );
        $data = $response->toArray();

        if (($data['status'] ?? '') === 'success') {
            return SubscriptionResponse::successful(
                subscriptionId: $data['data']['referenceCode'] ?? '',
                status: 'active',
                rawResponse: $data,
            );
        }

        return SubscriptionResponse::failed(
            errorCode: (string) ($data['errorCode'] ?? 'UNKNOWN'),
            errorMessage: $data['errorMessage'] ?? 'Iyzico abonelik oluşturma başarısız.',
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
        $body = [
            'locale' => 'tr',
            'subscriptionReferenceCode' => $subscriptionId,
        ];

        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);

        $headers = IyzicoHelper::generateHeaders(
            $this->config->get('api_key'),
            $this->config->get('secret_key'),
            $jsonBody,
        );

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/v2/subscription/cancel',
            $headers,
            $jsonBody,
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
            errorCode: (string) ($data['errorCode'] ?? 'UNKNOWN'),
            errorMessage: $data['errorMessage'] ?? 'Iyzico abonelik iptali başarısız.',
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
        $body = [
            'locale' => 'tr',
            'binNumber' => $binNumber,
            'price' => IyzicoHelper::formatAmount($amount),
        ];

        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);

        $headers = IyzicoHelper::generateHeaders(
            $this->config->get('api_key'),
            $this->config->get('secret_key'),
            $jsonBody,
        );

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payment/iyzi/installment',
            $headers,
            $jsonBody,
        );
        $data = $response->toArray();

        $installments = [];

        if (($data['status'] ?? '') === 'success' && isset($data['installmentDetails'])) {
            foreach ($data['installmentDetails'] as $detail) {
                if (!isset($detail['installmentPrices'])) {
                    continue;
                }

                foreach ($detail['installmentPrices'] as $inst) {
                    $count = (int) ($inst['installmentNumber'] ?? 0);
                    $total = (float) ($inst['totalPrice'] ?? 0);
                    $perInst = (float) ($inst['installmentPrice'] ?? 0);

                    if ($count > 0) {
                        $rate = $amount > 0 ? (($total - $amount) / $amount) * 100 : 0;

                        $installments[] = InstallmentInfo::create(
                            count: $count,
                            perInstallment: $perInst,
                            total: $total,
                            interestRate: round($rate, 2),
                        );
                    }
                }
            }
        }

        return $installments;
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['api_key', 'secret_key'];
    }

    protected function getBaseUrl(): string
    {
        // base_url config'den özelleştirilebilir
        return $this->config->get('base_url', self::LIVE_BASE_URL);
    }

    protected function getTestBaseUrl(): string
    {
        return $this->config->get('base_url', self::SANDBOX_BASE_URL);
    }

    /**
     * Ödeme API gövdesini oluşturur (pay ve initSecurePayment ortak).
     *
     * @param PaymentRequest $request Ödeme istek bilgileri
     *
     * @return array<string, mixed> API istek gövdesi
     */
    private function buildPaymentBody(PaymentRequest $request): array
    {
        $card = $request->getCard();
        $customer = $request->getCustomer();
        $billing = $request->getBillingAddress();
        $shipping = $request->getShippingAddress() ?? $billing;

        // Sepet ürünlerini hazırla
        $basketItems = [];
        foreach ($request->getCartItems() as $item) {
            $basketItems[] = [
                'id' => $item->id,
                'name' => $item->name,
                'category1' => $item->category,
                'itemType' => 'PHYSICAL',
                'price' => IyzicoHelper::formatAmount($item->price * $item->quantity),
            ];
        }

        // Sepet boşsa varsayılan ürün ekle
        if (empty($basketItems)) {
            $basketItems[] = [
                'id' => 'DEFAULT',
                'name' => $request->getDescription() ?: 'Ödeme',
                'category1' => 'Genel',
                'itemType' => 'PHYSICAL',
                'price' => IyzicoHelper::formatAmount($request->getAmount()),
            ];
        }

        $body = [
            'locale' => 'tr',
            'conversationId' => $request->getOrderId(),
            'price' => IyzicoHelper::formatAmount($request->getAmount()),
            'paidPrice' => IyzicoHelper::formatAmount($request->getAmount()),
            'currency' => 'TRY' === $request->getCurrency() ? 'TRY' : $request->getCurrency(),
            'installment' => $request->getInstallmentCount(),
            'paymentChannel' => 'WEB',
            'paymentGroup' => 'PRODUCT',
            'paymentCard' => [
                'cardHolderName' => $card?->cardHolderName ?? '',
                'cardNumber' => $card?->cardNumber ?? '',
                'expireMonth' => $card?->expireMonth ?? '',
                'expireYear' => $card?->expireYear ?? '',
                'cvc' => $card?->cvv ?? '',
                'registerCard' => '0',
            ],
            'buyer' => [
                'id' => $customer?->identityNumber ?: 'BUYER_' . $request->getOrderId(),
                'name' => $customer?->firstName ?? 'Ad',
                'surname' => $customer?->lastName ?? 'Soyad',
                'email' => $customer?->email ?? 'musteri@example.com',
                'identityNumber' => $customer?->identityNumber ?: '11111111111',
                'registrationAddress' => $billing?->address ?? 'Adres',
                'ip' => $customer?->ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                'city' => $billing?->city ?? 'Istanbul',
                'country' => $billing?->country ?? 'Turkey',
                'gsmNumber' => $customer?->phone ?? '',
            ],
            'billingAddress' => [
                'contactName' => $customer ? $customer->getFullName() : 'Ad Soyad',
                'city' => $billing?->city ?? 'Istanbul',
                'country' => $billing?->country ?? 'Turkey',
                'address' => $billing?->address ?? 'Adres',
            ],
            'shippingAddress' => [
                'contactName' => $customer ? $customer->getFullName() : 'Ad Soyad',
                'city' => $shipping?->city ?? $billing?->city ?? 'Istanbul',
                'country' => $shipping?->country ?? $billing?->country ?? 'Turkey',
                'address' => $shipping?->address ?? $billing?->address ?? 'Adres',
            ],
            'basketItems' => $basketItems,
        ];

        return $body;
    }

    /**
     * Gateway yanıtını PaymentResponse nesnesine dönüştürür.
     *
     * @param array<string, mixed> $data Gateway ham yanıtı
     * @param string $orderId Sipariş numarası
     *
     * @return PaymentResponse Standart ödeme yanıtı
     */
    private function parsePaymentResponse(array $data, string $orderId): PaymentResponse
    {
        if (($data['status'] ?? '') === 'success') {
            return PaymentResponse::successful(
                transactionId: $data['paymentId'] ?? '',
                orderId: $orderId ?: ($data['conversationId'] ?? ''),
                amount: isset($data['price']) ? (float) $data['price'] : 0.0,
                rawResponse: $data,
            );
        }

        return PaymentResponse::failed(
            errorCode: (string) ($data['errorCode'] ?? 'UNKNOWN'),
            errorMessage: $data['errorMessage'] ?? 'Iyzico ödeme başarısız.',
            rawResponse: $data,
        );
    }
}

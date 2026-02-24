<?php

declare(strict_types=1);

namespace Arpay\Gateways\Ipara;

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
 * iPara ödeme altyapısı gateway implementasyonu.
 *
 * iPara, Türkiye'de yaygın kullanılan bir sanal POS altyapısıdır.
 * Abonelik desteği bulunmamaktadır.
 *
 * Yapılandırma:
 * ```php
 * $gateway = Arpay::create('ipara', [
 *     'public_key'  => 'XXXXX',
 *     'private_key' => 'YYYYY',
 * ]);
 * ```
 *
 * @author Armağan Gökce
 */
class IparaGateway extends AbstractGateway implements PayableInterface, RefundableInterface, QueryableInterface, SecurePayableInterface, InstallmentQueryableInterface
{
    private const LIVE_BASE_URL = 'https://api.ipara.com';
    private const SANDBOX_BASE_URL = 'https://api-test.ipara.com';

    public function getName(): string
    {
        return 'iPara';
    }

    public function getShortName(): string
    {
        return 'ipara';
    }

    public function getSupportedFeatures(): array
    {
        return ['pay', 'payInstallment', 'refund', 'query', '3dsecure', 'installmentQuery'];
    }

    public function pay(PaymentRequest $request): PaymentResponse
    {
        $card = $request->getCard();
        if (null === $card) {
            return PaymentResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $body = $this->buildPaymentBody($request);
        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->buildAuthHeaders('/rest/payment/auth', $jsonBody);

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/rest/payment/auth',
            $headers,
            $jsonBody,
        );
        $data = $response->toArray();

        if (($data['result'] ?? '') === '1') {
            return PaymentResponse::successful(
                transactionId: $data['transactionId'] ?? '',
                orderId: $data['orderId'] ?? $request->getOrderId(),
                amount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return PaymentResponse::failed(
            errorCode: $data['errorCode'] ?? 'UNKNOWN',
            errorMessage: $data['errorMessage'] ?? 'iPara ödeme başarısız.',
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
            'orderId' => $request->getOrderId(),
            'amount' => MoneyFormatter::toDecimalString($request->getAmount()),
            'transactionId' => $request->getTransactionId(),
        ];

        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->buildAuthHeaders('/rest/payment/refund', $jsonBody);

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/rest/payment/refund',
            $headers,
            $jsonBody,
        );
        $data = $response->toArray();

        if (($data['result'] ?? '') === '1') {
            return RefundResponse::successful(
                transactionId: $data['transactionId'] ?? $request->getTransactionId(),
                refundedAmount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        return RefundResponse::failed(
            errorCode: $data['errorCode'] ?? 'UNKNOWN',
            errorMessage: $data['errorMessage'] ?? 'iPara iade başarısız.',
            rawResponse: $data,
        );
    }

    public function query(QueryRequest $request): QueryResponse
    {
        $body = [
            'orderId' => $request->getOrderId(),
            'transactionId' => $request->getTransactionId(),
        ];

        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->buildAuthHeaders('/rest/payment/inquiry', $jsonBody);

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/rest/payment/inquiry',
            $headers,
            $jsonBody,
        );
        $data = $response->toArray();

        if (($data['result'] ?? '') === '1') {
            $status = match ($data['status'] ?? '') {
                '1', 'approved' => PaymentStatus::Successful,
                '0', 'declined' => PaymentStatus::Failed,
                'pending' => PaymentStatus::Pending,
                default => PaymentStatus::Pending,
            };

            return QueryResponse::successful(
                transactionId: $data['transactionId'] ?? '',
                orderId: $data['orderId'] ?? '',
                amount: (float) ($data['amount'] ?? 0),
                status: $status,
                rawResponse: $data,
            );
        }

        return QueryResponse::failed(
            errorCode: $data['errorCode'] ?? 'UNKNOWN',
            errorMessage: $data['errorMessage'] ?? 'iPara sorgu başarısız.',
            rawResponse: $data,
        );
    }

    public function initSecurePayment(SecurePaymentRequest $request): SecureInitResponse
    {
        $card = $request->getCard();
        if (null === $card) {
            return SecureInitResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $body = $this->buildPaymentBody($request);
        $body['mode'] = 'T'; // 3D modu
        $body['callbackUrl'] = $request->getSuccessUrl() ?: $request->getCallbackUrl();

        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->buildAuthHeaders('/rest/payment/3dsecure', $jsonBody);

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/rest/payment/3dsecure',
            $headers,
            $jsonBody,
        );
        $data = $response->toArray();

        if (isset($data['threeDSecureHtml'])) {
            $html = base64_decode($data['threeDSecureHtml'], true);

            return SecureInitResponse::html($html, $data);
        }

        if (isset($data['redirectUrl'])) {
            return SecureInitResponse::redirect($data['redirectUrl'], [], $data);
        }

        return SecureInitResponse::failed(
            errorCode: $data['errorCode'] ?? 'UNKNOWN',
            errorMessage: $data['errorMessage'] ?? 'iPara 3D Secure başlatma başarısız.',
            rawResponse: $data,
        );
    }

    public function completeSecurePayment(SecureCallbackData $data): PaymentResponse
    {
        $result = $data->get('result', $data->get('mdStatus', ''));

        if ('1' === $result) {
            // 3D Secure doğrulamasından sonra ödemeyi tamamla
            $body = [
                'threeDSecureCode' => $data->get('threeDSecureCode', ''),
                'orderId' => $data->get('orderId', ''),
                'transactionId' => $data->get('transactionId', ''),
            ];

            $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);
            $headers = $this->buildAuthHeaders('/rest/payment/3dsecure/complete', $jsonBody);

            $response = $this->httpClient->post(
                $this->getActiveBaseUrl() . '/rest/payment/3dsecure/complete',
                $headers,
                $jsonBody,
            );
            $responseData = $response->toArray();

            if (($responseData['result'] ?? '') === '1') {
                return PaymentResponse::successful(
                    transactionId: $responseData['transactionId'] ?? '',
                    orderId: $responseData['orderId'] ?? $data->get('orderId', ''),
                    amount: (float) ($responseData['amount'] ?? 0),
                    rawResponse: $responseData,
                );
            }

            return PaymentResponse::failed(
                errorCode: $responseData['errorCode'] ?? 'UNKNOWN',
                errorMessage: $responseData['errorMessage'] ?? 'iPara 3D Secure ödeme tamamlama başarısız.',
                rawResponse: $responseData,
            );
        }

        return PaymentResponse::failed(
            errorCode: (string) $data->get('errorCode', 'UNKNOWN'),
            errorMessage: (string) $data->get('errorMessage', 'iPara 3D Secure doğrulama başarısız.'),
            rawResponse: $data->toArray(),
        );
    }

    public function queryInstallments(string $binNumber, float $amount): array
    {
        $body = [
            'binNumber' => $binNumber,
            'amount' => MoneyFormatter::toDecimalString($amount),
        ];

        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->buildAuthHeaders('/rest/payment/bin/installment', $jsonBody);

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/rest/payment/bin/installment',
            $headers,
            $jsonBody,
        );
        $data = $response->toArray();

        $installments = [];
        foreach ($data['installmentDetails'] ?? [] as $inst) {
            $count = (int) ($inst['installmentCount'] ?? 0);
            if ($count > 0) {
                $installments[] = InstallmentInfo::create(
                    count: $count,
                    perInstallment: (float) ($inst['installmentAmount'] ?? 0),
                    total: (float) ($inst['totalAmount'] ?? 0),
                    interestRate: (float) ($inst['interestRate'] ?? 0),
                );
            }
        }

        return $installments;
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['public_key', 'private_key'];
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
     * iPara API istekleri için kimlik doğrulama başlıklarını oluşturur.
     */
    private function buildAuthHeaders(string $endpoint, string $body = ''): array
    {
        $dateTime = gmdate('Y-m-d\TH:i:s');
        $publicKey = $this->config->get('public_key');
        $privateKey = $this->config->get('private_key');

        // iPara hash: SHA1( privateKey + publicKey + dateTime + requestBody )
        $hashStr = $privateKey . $publicKey . $dateTime . $body;
        $token = HashGenerator::sha1($hashStr);

        return [
            'Content-Type' => 'application/json',
            'Authorization' => $publicKey . ':' . $token,
            'TransactionDate' => $dateTime,
            'Version' => '1.0',
        ];
    }

    /**
     * Ödeme isteği için temel gövde parametrelerini oluşturur.
     */
    private function buildPaymentBody(PaymentRequest $request): array
    {
        $card = $request->getCard();
        $customer = $request->getCustomer();
        $billingAddress = $request->getBillingAddress();

        $body = [
            'orderId' => $request->getOrderId(),
            'amount' => MoneyFormatter::toDecimalString($request->getAmount()),
            'cardOwnerName' => $card->cardHolderName,
            'cardNumber' => $card->cardNumber,
            'cardExpireMonth' => $card->expireMonth,
            'cardExpireYear' => $card->expireYear,
            'cardCvc' => $card->cvv,
            'installment' => (string) $request->getInstallmentCount(),
            'currency' => $request->getCurrency(),
        ];

        if (null !== $customer) {
            $body['purchaser'] = [
                'name' => $customer->firstName,
                'surname' => $customer->lastName,
                'email' => $customer->email,
                'clientIp' => $customer->ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            ];
        }

        if (null !== $billingAddress) {
            $body['purchaser']['invoiceAddress'] = [
                'name' => $customer?->getFullName() ?? '',
                'address' => $billingAddress->address,
                'city' => $billingAddress->city,
                'country' => $billingAddress->country,
                'zipcode' => $billingAddress->zipCode,
            ];
        }

        // Sepet kalemleri
        $products = [];
        foreach ($request->getCartItems() as $item) {
            $products[] = [
                'productCode' => $item->id,
                'productName' => $item->name,
                'quantity' => (string) $item->quantity,
                'price' => MoneyFormatter::toDecimalString($item->price),
            ];
        }
        if (!empty($products)) {
            $body['products'] = $products;
        }

        return $body;
    }
}

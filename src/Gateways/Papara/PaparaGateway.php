<?php

declare(strict_types=1);

namespace Arpay\Gateways\Papara;

use Arpay\Contracts\PayableInterface;
use Arpay\Contracts\QueryableInterface;
use Arpay\Contracts\RefundableInterface;
use Arpay\DTO\PaymentRequest;
use Arpay\DTO\PaymentResponse;
use Arpay\DTO\QueryRequest;
use Arpay\DTO\QueryResponse;
use Arpay\DTO\RefundRequest;
use Arpay\DTO\RefundResponse;
use Arpay\Enums\PaymentStatus;
use Arpay\Gateways\AbstractGateway;
use Arpay\Support\MoneyFormatter;

/**
 * Papara Sanal POS ödeme altyapısı gateway implementasyonu.
 *
 * Papara, Türkiye'nin dijital cüzdan ve sanal POS hizmeti sunan fintech şirketidir.
 * Papara Sanal POS yalnızca ödeme, iade ve sorgu işlemlerini destekler.
 * 3D Secure, abonelik ve taksit sorgulama desteği bulunmamaktadır.
 *
 * Yapılandırma:
 * ```php
 * $gateway = Arpay::create('papara', [
 *     'api_key'     => 'XXXXX',
 *     'merchant_id' => 'YYYYY',
 * ]);
 * ```
 *
 * @author Armağan Gökce
 */
class PaparaGateway extends AbstractGateway implements PayableInterface, RefundableInterface, QueryableInterface
{
    private const LIVE_BASE_URL = 'https://merchant-api.papara.com';
    private const SANDBOX_BASE_URL = 'https://merchant-api-test.papara.com';

    public function getName(): string
    {
        return 'Papara';
    }

    public function getShortName(): string
    {
        return 'papara';
    }

    public function getSupportedFeatures(): array
    {
        return ['pay', 'payInstallment', 'refund', 'query'];
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['api_key', 'merchant_id'];
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
     * Papara API istekleri için standart başlıkları oluşturur.
     */
    private function buildHeaders(): array
    {
        return [
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'ApiKey'        => $this->config->get('api_key'),
        ];
    }

    public function pay(PaymentRequest $request): PaymentResponse
    {
        $card = $request->getCard();
        if ($card === null) {
            return PaymentResponse::failed('CARD_MISSING', 'Kart bilgileri gereklidir.');
        }

        $customer = $request->getCustomer();

        $body = [
            'merchantId'       => $this->config->get('merchant_id'),
            'referenceId'      => $request->getOrderId(),
            'amount'           => MoneyFormatter::toDecimalString($request->getAmount()),
            'currency'         => $this->mapCurrency($request->getCurrency()),
            'description'      => $request->getDescription() ?? 'Ödeme',
            'installmentCount' => $request->getInstallmentCount(),
            'cardHolderName'   => $card->cardHolderName,
            'cardNumber'       => $card->cardNumber,
            'expireMonth'      => $card->expireMonth,
            'expireYear'       => $card->expireYear,
            'cvc'              => $card->cvv,
            'buyerFirstName'   => $customer?->firstName ?? '',
            'buyerLastName'    => $customer?->lastName ?? '',
            'buyerEmail'       => $customer?->email ?? '',
            'buyerPhoneNumber' => $customer?->phone ?? '',
            'buyerIp'          => $customer?->ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
        ];

        // Sepet kalemleri
        $items = [];
        foreach ($request->getCartItems() as $item) {
            $items[] = [
                'itemId'     => $item->id,
                'itemName'   => $item->name,
                'itemPrice'  => MoneyFormatter::toDecimalString($item->price),
                'itemQuantity' => $item->quantity,
            ];
        }
        if (!empty($items)) {
            $body['items'] = $items;
        }

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payments',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['succeeded'] ?? false) === true) {
            $result = $data['data'] ?? $data;
            return PaymentResponse::successful(
                transactionId: (string) ($result['id'] ?? $result['paymentId'] ?? ''),
                orderId: (string) ($result['referenceId'] ?? $request->getOrderId()),
                amount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        $error = $data['error'] ?? $data;
        return PaymentResponse::failed(
            errorCode: (string) ($error['code'] ?? $data['errorCode'] ?? 'UNKNOWN'),
            errorMessage: $error['message'] ?? $data['errorMessage'] ?? 'Papara ödeme başarısız.',
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
            'merchantId'    => $this->config->get('merchant_id'),
            'paymentId'     => $request->getTransactionId(),
            'referenceId'   => $request->getOrderId(),
            'refundAmount'  => MoneyFormatter::toDecimalString($request->getAmount()),
            'description'   => $request->getReason() ?? 'İade',
        ];

        $response = $this->httpClient->post(
            $this->getActiveBaseUrl() . '/payments/refund',
            $this->buildHeaders(),
            json_encode($body, JSON_THROW_ON_ERROR),
        );
        $data = $response->toArray();

        if (($data['succeeded'] ?? false) === true) {
            $result = $data['data'] ?? $data;
            return RefundResponse::successful(
                transactionId: (string) ($result['id'] ?? $request->getTransactionId()),
                refundedAmount: $request->getAmount(),
                rawResponse: $data,
            );
        }

        $error = $data['error'] ?? $data;
        return RefundResponse::failed(
            errorCode: (string) ($error['code'] ?? 'UNKNOWN'),
            errorMessage: $error['message'] ?? 'Papara iade başarısız.',
            rawResponse: $data,
        );
    }

    public function query(QueryRequest $request): QueryResponse
    {
        $paymentId = $request->getTransactionId() ?: $request->getOrderId();

        $response = $this->httpClient->get(
            $this->getActiveBaseUrl() . '/payments/' . $paymentId,
            $this->buildHeaders(),
        );
        $data = $response->toArray();

        if (($data['succeeded'] ?? false) === true) {
            $result = $data['data'] ?? $data;

            $status = match ((int) ($result['status'] ?? -1)) {
                0       => PaymentStatus::Pending,
                1       => PaymentStatus::Successful,
                2       => PaymentStatus::Refunded,
                3       => PaymentStatus::Cancelled,
                default => PaymentStatus::Failed,
            };

            return QueryResponse::successful(
                transactionId: (string) ($result['id'] ?? ''),
                orderId: (string) ($result['referenceId'] ?? ''),
                amount: (float) ($result['amount'] ?? 0),
                status: $status,
                rawResponse: $data,
            );
        }

        $error = $data['error'] ?? $data;
        return QueryResponse::failed(
            errorCode: (string) ($error['code'] ?? 'UNKNOWN'),
            errorMessage: $error['message'] ?? 'Papara sorgu başarısız.',
            rawResponse: $data,
        );
    }

    /**
     * Para birimi kodunu Papara formatına dönüştürür.
     */
    private function mapCurrency(string $currency): int
    {
        return match ($currency) {
            'TRY' => 0,
            'USD' => 1,
            'EUR' => 2,
            'GBP' => 3,
            default => 0,
        };
    }
}

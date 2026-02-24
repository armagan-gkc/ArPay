<?php

declare(strict_types=1);

namespace Arpay\DTO;

use Arpay\Enums\PaymentStatus;

/**
 * Sorgu sonucu veri nesnesi.
 *
 * Ödeme durumu sorgulama sonucunu standart bir yapıda sunar.
 *
 * @author Armağan Gökce
 */
class QueryResponse implements \JsonSerializable
{
    public function __construct(
        protected readonly bool $successful,
        protected readonly string $transactionId = '',
        protected readonly string $orderId = '',
        protected readonly float $amount = 0.0,
        protected readonly PaymentStatus $status = PaymentStatus::Pending,
        protected readonly string $errorCode = '',
        protected readonly string $errorMessage = '',
        protected readonly array $rawResponse = [],
    ) {}

    /**
     * Başarılı sorgu yanıtı oluşturur.
     */
    public static function successful(
        string $transactionId,
        string $orderId = '',
        float $amount = 0.0,
        PaymentStatus $status = PaymentStatus::Successful,
        array $rawResponse = [],
    ): self {
        return new self(
            successful: true,
            transactionId: $transactionId,
            orderId: $orderId,
            amount: $amount,
            status: $status,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Başarısız sorgu yanıtı oluşturur.
     */
    public static function failed(
        string $errorCode = '',
        string $errorMessage = '',
        array $rawResponse = [],
    ): self {
        return new self(
            successful: false,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            rawResponse: $rawResponse,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getPaymentStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    /**
     * Yanıtı dizi olarak döndürür.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'successful' => $this->successful,
            'transaction_id' => $this->transactionId,
            'order_id' => $this->orderId,
            'amount' => $this->amount,
            'status' => $this->status->value,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'raw_response' => $this->rawResponse,
        ];
    }

    /**
     * JSON serileştirme desteği.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

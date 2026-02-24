<?php

declare(strict_types=1);

namespace Arpay\DTO;

/**
 * İade sonucu veri nesnesi.
 *
 * Gateway'den dönen iade sonucunu standart bir yapıda sunar.
 *
 * @author Armağan Gökce
 */
class RefundResponse implements \JsonSerializable
{
    public function __construct(
        protected readonly bool $successful,
        protected readonly string $transactionId = '',
        protected readonly float $refundedAmount = 0.0,
        protected readonly string $errorCode = '',
        protected readonly string $errorMessage = '',
        protected readonly array $rawResponse = [],
    ) {}

    /**
     * Başarılı iade yanıtı oluşturur.
     */
    public static function successful(
        string $transactionId,
        float $refundedAmount = 0.0,
        array $rawResponse = [],
    ): self {
        return new self(
            successful: true,
            transactionId: $transactionId,
            refundedAmount: $refundedAmount,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Başarısız iade yanıtı oluşturur.
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

    public function getRefundedAmount(): float
    {
        return $this->refundedAmount;
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
            'refunded_amount' => $this->refundedAmount,
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

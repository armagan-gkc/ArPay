<?php

declare(strict_types=1);

namespace Arpay\DTO;

/**
 * Abonelik sonucu veri nesnesi.
 *
 * Abonelik oluşturma veya iptal işleminin sonucunu sunar.
 *
 * @author Armağan Gökce
 */
class SubscriptionResponse implements \JsonSerializable
{
    public function __construct(
        protected readonly bool $successful,
        protected readonly string $subscriptionId = '',
        protected readonly string $status = '',
        protected readonly string $errorCode = '',
        protected readonly string $errorMessage = '',
        protected readonly array $rawResponse = [],
    ) {
    }

    /**
     * Başarılı abonelik yanıtı oluşturur.
     */
    public static function successful(
        string $subscriptionId,
        string $status = 'active',
        array $rawResponse = [],
    ): self {
        return new self(
            successful: true,
            subscriptionId: $subscriptionId,
            status: $status,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Başarısız abonelik yanıtı oluşturur.
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

    public function getSubscriptionId(): string
    {
        return $this->subscriptionId;
    }

    public function getStatus(): string
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
            'successful'      => $this->successful,
            'subscription_id' => $this->subscriptionId,
            'status'          => $this->status,
            'error_code'      => $this->errorCode,
            'error_message'   => $this->errorMessage,
            'raw_response'    => $this->rawResponse,
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

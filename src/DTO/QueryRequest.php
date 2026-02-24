<?php

declare(strict_types=1);

namespace Arpay\DTO;

/**
 * Sorgu talebi veri nesnesi.
 *
 * Daha önce yapılmış bir ödeme işleminin durumunu
 * sorgulamak için gerekli bilgileri tutar.
 *
 * @author Armağan Gökce
 */
class QueryRequest
{
    protected string $transactionId = '';
    protected string $orderId = '';

    /** @var array<string, mixed> */
    protected array $metadata = [];

    public static function create(): static
    {
        return new static();
    }

    /**
     * Gateway işlem numarasıyla sorgulama yapar.
     */
    public function transactionId(string $transactionId): static
    {
        $this->transactionId = $transactionId;

        return $this;
    }

    /**
     * Sipariş numarasıyla sorgulama yapar.
     */
    public function orderId(string $orderId): static
    {
        $this->orderId = $orderId;

        return $this;
    }

    /**
     * Gateway'e özel ek parametre ekler.
     */
    public function meta(string $key, mixed $value): static
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}

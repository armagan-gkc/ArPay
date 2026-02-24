<?php

declare(strict_types=1);

namespace Arpay\DTO;

/**
 * İade talebi veri nesnesi.
 *
 * Tam veya kısmi iade için gerekli bilgileri tutar.
 *
 * @author Armağan Gökce
 *
 * @phpstan-consistent-constructor
 */
class RefundRequest
{
    protected string $transactionId = '';
    protected string $orderId = '';
    protected float $amount = 0.0;
    protected string $reason = '';

    /** @var array<string, mixed> */
    protected array $metadata = [];

    public static function create(): static
    {
        return new static(); // @phpstan-ignore new.static
    }

    /**
     * Gateway işlem numarasını ayarlar.
     */
    public function transactionId(string $transactionId): static
    {
        $this->transactionId = $transactionId;

        return $this;
    }

    /**
     * Sipariş numarasını ayarlar.
     */
    public function orderId(string $orderId): static
    {
        $this->orderId = $orderId;

        return $this;
    }

    /**
     * İade tutarını ayarlar.
     *
     * Kısmi iade için orijinal tutardan düşük bir değer girin.
     */
    public function amount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * İade nedenini ayarlar.
     */
    public function reason(string $reason): static
    {
        $this->reason = $reason;

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

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}

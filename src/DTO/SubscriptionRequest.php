<?php

declare(strict_types=1);

namespace Arpay\DTO;

/**
 * Abonelik talebi veri nesnesi.
 *
 * Tekrarlayan ödeme planı oluşturmak için gerekli bilgileri tutar.
 *
 * @author Armağan Gökce
 *
 * @phpstan-consistent-constructor
 */
class SubscriptionRequest
{
    protected string $planName = '';
    protected float $amount = 0.0;
    protected string $currency = 'TRY';
    protected string $period = 'monthly';
    protected int $periodInterval = 1;
    protected ?CreditCard $card = null;
    protected ?Customer $customer = null;

    /** @var array<string, mixed> */
    protected array $metadata = [];

    public static function create(): static
    {
        return new static(); // @phpstan-ignore new.static
    }

    /**
     * Plan adını ayarlar.
     */
    public function planName(string $name): static
    {
        $this->planName = $name;

        return $this;
    }

    /**
     * Ödeme tutarını ayarlar.
     */
    public function amount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Para birimini ayarlar.
     */
    public function currency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Ödeme periyodunu ayarlar.
     *
     * @param string $period 'daily', 'weekly', 'monthly', 'yearly'
     */
    public function period(string $period): static
    {
        $this->period = $period;

        return $this;
    }

    /**
     * Periyot aralığını ayarlar.
     *
     * Örnek: period('monthly') + periodInterval(3) = 3 ayda bir
     */
    public function periodInterval(int $interval): static
    {
        $this->periodInterval = max(1, $interval);

        return $this;
    }

    /**
     * Kart bilgilerini ayarlar.
     */
    public function card(CreditCard $card): static
    {
        $this->card = $card;

        return $this;
    }

    /**
     * Müşteri bilgilerini ayarlar.
     */
    public function customer(Customer $customer): static
    {
        $this->customer = $customer;

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

    public function getPlanName(): string
    {
        return $this->planName;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getPeriod(): string
    {
        return $this->period;
    }

    public function getPeriodInterval(): int
    {
        return $this->periodInterval;
    }

    public function getCard(): ?CreditCard
    {
        return $this->card;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}

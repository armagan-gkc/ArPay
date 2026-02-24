<?php

declare(strict_types=1);

namespace Arpay\DTO;

use Arpay\Enums\Currency;

/**
 * Ödeme talebi veri nesnesi (Builder pattern).
 *
 * Ödeme bilgilerini zincirleme (fluent) metotlarla oluşturmanızı sağlar.
 * Tüm gateway'ler için ortak bir ödeme istek yapısıdır.
 *
 * Kullanım:
 * ```php
 * $request = PaymentRequest::create()
 *     ->amount(150.00)
 *     ->currency('TRY')
 *     ->orderId('SIP-001')
 *     ->description('Test siparişi')
 *     ->installmentCount(1)
 *     ->card($card)
 *     ->customer($customer)
 *     ->billingAddress($address)
 *     ->addCartItem($item);
 * ```
 *
 * @author Armağan Gökce
 *
 * @phpstan-consistent-constructor
 */
class PaymentRequest
{
    /** @var float Ödeme tutarı (TL) */
    protected float $amount = 0.0;

    /** @var string Para birimi */
    protected string $currency = 'TRY';

    /** @var string Sipariş numarası */
    protected string $orderId = '';

    /** @var string Ödeme açıklaması */
    protected string $description = '';

    /** @var int Taksit sayısı (1 = tek çekim) */
    protected int $installmentCount = 1;

    /** @var null|CreditCard Kart bilgileri */
    protected ?CreditCard $card = null;

    /** @var null|Customer Müşteri bilgileri */
    protected ?Customer $customer = null;

    /** @var null|Address Fatura adresi */
    protected ?Address $billingAddress = null;

    /** @var null|Address Teslimat adresi */
    protected ?Address $shippingAddress = null;

    /** @var CartItem[] Sepet ürünleri */
    protected array $cartItems = [];

    /** @var array<string, mixed> Gateway'e özel ek parametreler */
    protected array $metadata = [];

    /**
     * Yeni ödeme talebi oluşturur (Builder başlangıcı).
     */
    public static function create(): static
    {
        return new static(); // @phpstan-ignore new.static
    }

    /**
     * Ödeme tutarını ayarlar.
     *
     * @param float $amount Tutar (TL cinsinden, örn: 150.00)
     */
    public function amount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Para birimini ayarlar.
     *
     * @param Currency|string $currency Para birimi ('TRY', 'USD', 'EUR' veya Currency enum)
     */
    public function currency(Currency|string $currency): static
    {
        $this->currency = $currency instanceof Currency ? $currency->value : $currency;

        return $this;
    }

    /**
     * Sipariş numarasını ayarlar.
     *
     * @param string $orderId Benzersiz sipariş numarası
     */
    public function orderId(string $orderId): static
    {
        $this->orderId = $orderId;

        return $this;
    }

    /**
     * Ödeme açıklamasını ayarlar.
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Taksit sayısını ayarlar.
     *
     * @param int $count Taksit sayısı (1 = tek çekim, 2-12 = taksitli)
     */
    public function installmentCount(int $count): static
    {
        $this->installmentCount = max(1, $count);

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
     * Fatura adresini ayarlar.
     */
    public function billingAddress(Address $address): static
    {
        $this->billingAddress = $address;

        return $this;
    }

    /**
     * Teslimat adresini ayarlar.
     */
    public function shippingAddress(Address $address): static
    {
        $this->shippingAddress = $address;

        return $this;
    }

    /**
     * Sepete ürün ekler.
     */
    public function addCartItem(CartItem $item): static
    {
        $this->cartItems[] = $item;

        return $this;
    }

    /**
     * Birden fazla sepet ürünü ekler.
     *
     * @param CartItem[] $items
     */
    public function cartItems(array $items): static
    {
        $this->cartItems = $items;

        return $this;
    }

    /**
     * Gateway'e özel ek parametre ekler.
     *
     * Bu alan gateway'lerin kendine özgü parametreleri için kullanılır.
     */
    public function meta(string $key, mixed $value): static
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /* ---------------------------------------------------------------
     * Getter metotları — gateway'ler bu metotları kullanarak
     * ödeme bilgilerine erişir.
     * --------------------------------------------------------------- */

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getInstallmentCount(): int
    {
        return $this->installmentCount;
    }

    public function getCard(): ?CreditCard
    {
        return $this->card;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function getBillingAddress(): ?Address
    {
        return $this->billingAddress;
    }

    public function getShippingAddress(): ?Address
    {
        return $this->shippingAddress;
    }

    /**
     * @return CartItem[]
     */
    public function getCartItems(): array
    {
        return $this->cartItems;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Belirli bir meta değerini döndürür.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}

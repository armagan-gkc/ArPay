<?php

declare(strict_types=1);

namespace Arpay\DTO;

/**
 * Sepet ürünü veri nesnesi.
 *
 * Ödeme işlemine dahil edilen bir sepet ürününü temsil eder.
 * Iyzico gibi bazı gateway'ler sepet detayını zorunlu tutar.
 *
 * @author Armağan Gökce
 */
class CartItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $category,
        public readonly float $price,
        public readonly int $quantity = 1,
    ) {}

    /**
     * Yeni sepet ürünü oluşturur.
     */
    public static function create(
        string $id,
        string $name,
        string $category,
        float $price,
        int $quantity = 1,
    ): self {
        return new self(
            id: $id,
            name: $name,
            category: $category,
            price: $price,
            quantity: $quantity,
        );
    }

    /**
     * Ürünün toplam tutarını döndürür (birim fiyat × adet).
     */
    public function getTotalPrice(): float
    {
        return $this->price * $this->quantity;
    }
}

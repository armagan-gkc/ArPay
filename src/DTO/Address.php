<?php

declare(strict_types=1);

namespace Arpay\DTO;

/**
 * Adres bilgileri veri nesnesi.
 *
 * Fatura veya teslimat adresi bilgilerini tutar.
 * Bazı gateway'ler (Iyzico gibi) adres bilgisini zorunlu tutar.
 *
 * @author Armağan Gökce
 */
class Address
{
    public function __construct(
        public readonly string $address,
        public readonly string $city,
        public readonly string $district = '',
        public readonly string $zipCode = '',
        public readonly string $country = 'Turkey',
    ) {}

    /**
     * Yeni adres nesnesi oluşturur.
     */
    public static function create(
        string $address,
        string $city,
        string $district = '',
        string $zipCode = '',
        string $country = 'Turkey',
    ): self {
        return new self(
            address: $address,
            city: $city,
            district: $district,
            zipCode: $zipCode,
            country: $country,
        );
    }
}

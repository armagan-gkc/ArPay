<?php

declare(strict_types=1);

namespace Arpay\DTO;

/**
 * Müşteri bilgileri veri nesnesi.
 *
 * Ödeme yapan müşterinin kişisel bilgilerini tutar.
 * Bazı gateway'ler (Iyzico gibi) bu bilgileri zorunlu tutar.
 *
 * @author Armağan Gökce
 */
class Customer
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $phone = '',
        public readonly string $ip = '',
        public readonly string $identityNumber = '',
    ) {
    }

    /**
     * Yeni müşteri nesnesi oluşturur.
     */
    public static function create(
        string $firstName,
        string $lastName,
        string $email,
        string $phone = '',
        string $ip = '',
        string $identityNumber = '',
    ): self {
        return new self(
            firstName: $firstName,
            lastName: $lastName,
            email: $email,
            phone: $phone,
            ip: $ip ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            identityNumber: $identityNumber,
        );
    }

    /**
     * Müşterinin tam adını döndürür.
     */
    public function getFullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }
}

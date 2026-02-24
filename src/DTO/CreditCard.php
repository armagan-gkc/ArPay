<?php

declare(strict_types=1);

namespace Arpay\DTO;

use Arpay\Enums\CardType;
use Arpay\Exceptions\InvalidParameterException;

/**
 * Kredi kartı bilgileri veri nesnesi.
 *
 * Kart sahibi adı, kart numarası, son kullanma tarihi ve CVV
 * bilgilerini tutar. Luhn algoritmasıyla kart numarası doğrulaması yapar.
 *
 * Kullanım:
 * ```php
 * $card = CreditCard::create(
 *     cardHolderName: 'Armağan Gökce',
 *     cardNumber: '5528790000000008',
 *     expireMonth: '12',
 *     expireYear: '2030',
 *     cvv: '123',
 * );
 * ```
 *
 * @author Armağan Gökce
 */
class CreditCard
{
    /**
     * @param string $cardHolderName Kart sahibinin adı soyadı
     * @param string $cardNumber Kart numarası (boşluk/tire temizlenmiş)
     * @param string $expireMonth Son kullanma ayı (01-12)
     * @param string $expireYear Son kullanma yılı (4 haneli)
     * @param string $cvv Güvenlik kodu (3 veya 4 hane)
     */
    public function __construct(
        public readonly string $cardHolderName,
        public readonly string $cardNumber,
        public readonly string $expireMonth,
        public readonly string $expireYear,
        public readonly string $cvv,
    ) {}

    /**
     * Yeni kart bilgisi nesnesi oluşturur.
     *
     * Named argument kullanımı ile okunabilir kart oluşturma sağlar.
     *
     * @throws InvalidParameterException Geçersiz kart bilgisi durumunda
     */
    public static function create(
        string $cardHolderName,
        string $cardNumber,
        string $expireMonth,
        string $expireYear,
        string $cvv,
    ): self {
        // Kart numarasındaki boşluk ve tireleri temizle
        $cardNumber = preg_replace('/[\s\-]/', '', $cardNumber) ?? $cardNumber;

        // Ay değerini 2 haneye standartlaştır
        $expireMonth = str_pad($expireMonth, 2, '0', STR_PAD_LEFT);

        // Yıl değerini 4 haneye standartlaştır
        if (2 === strlen($expireYear)) {
            $expireYear = '20' . $expireYear;
        }

        return new self(
            cardHolderName: $cardHolderName,
            cardNumber: $cardNumber,
            expireMonth: $expireMonth,
            expireYear: $expireYear,
            cvv: $cvv,
        );
    }

    /**
     * Kart numarasının Luhn algoritmasına göre geçerli olup olmadığını kontrol eder.
     */
    public function isValid(): bool
    {
        return self::luhnCheck($this->cardNumber);
    }

    /**
     * Kart numarasının ilk 6 hanesini (BIN) döndürür.
     */
    public function getBin(): string
    {
        return substr($this->cardNumber, 0, 6);
    }

    /**
     * Kart tipini (Visa, MasterCard, Troy, Amex) algılar.
     */
    public function getCardType(): ?CardType
    {
        return CardType::detectFromBin($this->cardNumber);
    }

    /**
     * Kart numarasının maskelenmiş halini döndürür.
     *
     * Örnek: "552879******0008"
     */
    public function getMaskedNumber(): string
    {
        $length = strlen($this->cardNumber);

        if ($length < 10) {
            return str_repeat('*', $length);
        }

        return substr($this->cardNumber, 0, 6)
            . str_repeat('*', $length - 10)
            . substr($this->cardNumber, -4);
    }

    /**
     * Luhn algoritması ile kart numarasını doğrular.
     *
     * @param string $number Kontrol edilecek kart numarası
     */
    public static function luhnCheck(string $number): bool
    {
        $number = preg_replace('/\D/', '', $number) ?? '';

        if ('' === $number || strlen($number) < 13 || strlen($number) > 19) {
            return false;
        }

        $sum = 0;
        $alternate = false;

        for ($i = strlen($number) - 1; $i >= 0; --$i) {
            $digit = (int) $number[$i];

            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $alternate = !$alternate;
        }

        return 0 === $sum % 10;
    }
}

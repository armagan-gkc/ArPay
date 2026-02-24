<?php

declare(strict_types=1);

namespace Arpay\Enums;

/**
 * Kart tipi değerleri.
 *
 * Kredi/banka kartının markasını belirtir.
 *
 * @author Armağan Gökce
 */
enum CardType: string
{
    /** Visa */
    case Visa = 'visa';

    /** MasterCard */
    case MasterCard = 'mastercard';

    /** Troy (Türkiye yerli kart şeması) */
    case Troy = 'troy';

    /** American Express */
    case Amex = 'amex';

    /**
     * BIN numarasından kart tipini tahmin eder.
     *
     * @param string $bin Kart numarasının ilk 6 hanesi
     */
    public static function detectFromBin(string $bin): ?self
    {
        $bin = preg_replace('/\D/', '', $bin);

        if (null === $bin || strlen($bin) < 1) {
            return null;
        }

        // Visa: 4 ile başlar
        if (str_starts_with($bin, '4')) {
            return self::Visa;
        }

        // MasterCard: 51-55 veya 2221-2720 aralığı
        $firstTwo = (int) substr($bin, 0, 2);
        $firstFour = strlen($bin) >= 4 ? (int) substr($bin, 0, 4) : 0;

        if (($firstTwo >= 51 && $firstTwo <= 55) || ($firstFour >= 2221 && $firstFour <= 2720)) {
            return self::MasterCard;
        }

        // Troy: 979200-979299 aralığı (BKM tanımlı)
        if (strlen($bin) >= 6) {
            $firstSix = (int) substr($bin, 0, 6);
            if ($firstSix >= 979200 && $firstSix <= 979299) {
                return self::Troy;
            }
        }

        // Amex: 34 veya 37 ile başlar
        if (34 === $firstTwo || 37 === $firstTwo) {
            return self::Amex;
        }

        return null;
    }
}

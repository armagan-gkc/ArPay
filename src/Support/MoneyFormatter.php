<?php

declare(strict_types=1);

namespace Arpay\Support;

/**
 * Para birimi biçimlendirme yardımcı sınıfı.
 *
 * Farklı ödeme altyapıları farklı tutar formatları kullanır:
 * - PayTR: Kuruş cinsinden integer (150.00 TL → 15000)
 * - Iyzico: Ondalıklı string ("150.00")
 * - Diğerleri: Değişken
 *
 * Bu sınıf dönüşümleri merkezi tek noktada yönetir.
 *
 * @author Armağan Gökce
 */
class MoneyFormatter
{
    /**
     * TL tutarını kuruşa çevirir.
     *
     * Örnek: 150.00 → 15000, 99.90 → 9990
     *
     * @param float $amount TL cinsinden tutar
     *
     * @return int Kuruş cinsinden tutar
     */
    public static function toPenny(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Kuruş tutarını TL'ye çevirir.
     *
     * Örnek: 15000 → "150.00", 9990 → "99.90"
     *
     * @param int $penny Kuruş cinsinden tutar
     *
     * @return string Ondalıklı TL tutarı
     */
    public static function toDecimal(int $penny): string
    {
        return number_format($penny / 100, 2, '.', '');
    }

    /**
     * Float tutarı ondalıklı string'e çevirir.
     *
     * Örnek: 150.0 → "150.00", 99.9 → "99.90"
     *
     * @param float $amount TL cinsinden tutar
     *
     * @return string 2 ondalıklı string tutar
     */
    public static function toDecimalString(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Kuruş (int) veya string tutarı float'a çevirir.
     *
     * Integer verilirse kuruştan TL'ye çevirir (15000 → 150.0).
     * String verilirse doğrudan float'a cast eder.
     *
     * @param int|string $amount Kuruş (int) veya string tutar değeri
     *
     * @return float Float tutar
     */
    public static function toFloat(int|string $amount): float
    {
        if (is_int($amount)) {
            return $amount / 100;
        }

        return (float) $amount;
    }
}

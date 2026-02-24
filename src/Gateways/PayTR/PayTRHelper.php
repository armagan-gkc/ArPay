<?php

declare(strict_types=1);

namespace Arpay\Gateways\PayTR;

use Arpay\Support\Config;
use Arpay\Support\HashGenerator;
use Arpay\Support\MoneyFormatter;

/**
 * PayTR'a özel yardımcı metotlar.
 *
 * Token oluşturma, tutar dönüşümü ve PayTR API'sine
 * özgü veri hazırlama işlemleri bu sınıfta toplanmıştır.
 *
 * @author Armağan Gökce
 */
class PayTRHelper
{
    /**
     * PayTR ödeme token'ı oluşturur (iframe/direct API).
     *
     * PayTR token oluşturma formülü:
     * hash_str = merchant_id + user_ip + merchant_oid + email + payment_amount
     *            + user_basket + no_installment + max_installment + currency + test_mode
     * token = base64( hmac_sha256( hash_str, merchant_key + merchant_salt ) )
     *
     * @param array<string, mixed> $params Token parametreleri
     * @param Config $config Gateway yapılandırması
     *
     * @return string Base64 kodlanmış HMAC token
     */
    public static function generateToken(array $params, Config $config): string
    {
        $hashStr = implode('', [
            $config->get('merchant_id'),
            $params['user_ip'] ?? '',
            $params['merchant_oid'] ?? '',
            $params['email'] ?? '',
            (string) ($params['payment_amount'] ?? ''),
            $params['user_basket'] ?? '',
            $params['no_installment'] ?? '0',
            $params['max_installment'] ?? '0',
            $params['currency'] ?? 'TL',
            $params['test_mode'] ?? '0',
        ]);

        $key = $config->get('merchant_key') . $config->get('merchant_salt');

        return HashGenerator::hmacSha256Base64($hashStr, $key);
    }

    /**
     * PayTR iade token'ı oluşturur.
     *
     * @param string $merchantOid Sipariş numarası
     * @param int $amount İade tutarı (kuruş)
     * @param Config $config Gateway yapılandırması
     *
     * @return string Base64 kodlanmış HMAC token
     */
    public static function generateRefundToken(string $merchantOid, int $amount, Config $config): string
    {
        $hashStr = implode('', [
            $config->get('merchant_id'),
            $merchantOid,
            (string) $amount,
            $config->get('merchant_salt'),
        ]);

        return HashGenerator::hmacSha256Base64($hashStr, $config->get('merchant_key'));
    }

    /**
     * TL tutarını PayTR'ın beklediği kuruş formatına çevirir.
     *
     * PayTR tutar olarak kuruş cinsinden integer bekler.
     * Örnek: 150.00 TL → 15000
     *
     * @param float $amount TL cinsinden tutar
     *
     * @return int Kuruş cinsinden tutar
     */
    public static function formatAmount(float $amount): int
    {
        return MoneyFormatter::toPenny($amount);
    }

    /**
     * Sepet ürünlerini PayTR'ın beklediği Base64-JSON formatına çevirir.
     *
     * PayTR basket formatı: base64(json_encode([["Ürün Adı", "Fiyat", Adet], ...]))
     *
     * @param array<int, array{name: string, price: float, quantity: int}> $items
     *
     * @return string Base64 kodlanmış JSON sepet
     */
    public static function formatBasket(array $items): string
    {
        $basket = [];

        foreach ($items as $item) {
            $basket[] = [
                $item['name'],
                (string) MoneyFormatter::toPenny($item['price']),
                $item['quantity'],
            ];
        }

        // Sepet boşsa varsayılan ürün ekle
        if (empty($basket)) {
            $basket[] = ['Ödeme', '0', 1];
        }

        return base64_encode(json_encode($basket, JSON_THROW_ON_ERROR));
    }

    /**
     * PayTR callback hash doğrulaması yapar.
     *
     * @param string $merchantOid Sipariş numarası
     * @param string $merchantSalt Merchant salt
     * @param string $merchantKey Merchant key
     * @param string $status Ödeme durumu ('success' veya 'failed')
     * @param string $totalAmount Toplam tutar (kuruş string)
     * @param string $expectedHash Beklenen hash değeri
     *
     * @return bool Hash doğrulama sonucu
     */
    public static function verifyCallback(
        string $merchantOid,
        string $merchantSalt,
        string $merchantKey,
        string $status,
        string $totalAmount,
        string $expectedHash,
    ): bool {
        $hashStr = $merchantOid . $merchantSalt . $status . $totalAmount;
        $calculatedHash = HashGenerator::hmacSha256Base64($hashStr, $merchantKey);

        return hash_equals($calculatedHash, $expectedHash);
    }
}

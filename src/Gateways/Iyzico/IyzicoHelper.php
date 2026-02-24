<?php

declare(strict_types=1);

namespace Arpay\Gateways\Iyzico;

use Arpay\Support\MoneyFormatter;

/**
 * Iyzico'ya özel yardımcı metotlar.
 *
 * Iyzico API'si Authorization header oluşturmak için PKI string
 * ve HMAC-SHA256 hash formatı kullanır.
 *
 * @author Armağan Gökce
 */
class IyzicoHelper
{
    /**
     * Iyzico Authorization header'ı oluşturur.
     *
     * Format: IYZWS {apiKey}:{hash}
     * Hash = Base64( SHA1( apiKey + randomHeaderValue + secretKey + requestBody ) )
     *
     * @param string $apiKey    API anahtarı
     * @param string $secretKey Gizli anahtar
     * @param string $body      İstek gövdesi (JSON string)
     * @return array<string, string> İsteke eklenecek header'lar
     */
    public static function generateHeaders(string $apiKey, string $secretKey, string $body = ''): array
    {
        $randomHeaderValue = (string) microtime(true);

        $hashStr = $apiKey . $randomHeaderValue . $secretKey . $body;
        $hash = base64_encode(sha1($hashStr, true));

        return [
            'Authorization'        => "IYZWS {$apiKey}:{$hash}",
            'x-iyzi-rnd'           => $randomHeaderValue,
            'Content-Type'         => 'application/json',
            'x-iyzi-client-version' => 'arpay-php-1.0',
        ];
    }

    /**
     * Iyzico PKI (Public Key Infrastructure) string oluşturur.
     *
     * Iyzico API'si bazı endpoint'lerde request body yerine
     * PKI string formatında veri bekler.
     *
     * @param array<string, mixed> $params Parametre çiftleri
     * @return string PKI formatında string
     */
    public static function buildPkiString(array $params): string
    {
        $parts = [];

        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $parts[] = "{$key}=[" . implode(', ', array_map(
                    fn ($item) => is_array($item) ? self::buildPkiString($item) : (string) $item,
                    $value,
                )) . ']';
            } else {
                $parts[] = "{$key}={$value}";
            }
        }

        return '[' . implode(',', $parts) . ']';
    }

    /**
     * Tutarı Iyzico'nun beklediği formata çevirir.
     *
     * Iyzico ondalıklı string tutar bekler: "150.00"
     *
     * @param float $amount TL cinsinden tutar
     * @return string Iyzico formatında tutar
     */
    public static function formatAmount(float $amount): string
    {
        return MoneyFormatter::toDecimalString($amount);
    }

    /**
     * Sepet ürünlerini Iyzico formatına dönüştürür.
     *
     * @param array<int, array{id: string, name: string, category: string, price: float}> $items
     * @return array<int, array<string, string>>
     */
    public static function formatBasketItems(array $items): array
    {
        $basket = [];

        foreach ($items as $item) {
            $basket[] = [
                'id'              => $item['id'],
                'name'            => $item['name'],
                'category1'       => $item['category'],
                'itemType'        => 'PHYSICAL',
                'price'           => self::formatAmount($item['price']),
            ];
        }

        return $basket;
    }
}

<?php

declare(strict_types=1);

namespace Arpay\Support;

/**
 * Hash / imza oluşturma yardımcı sınıfı.
 *
 * Türk ödeme altyapılarının çoğu HMAC tabanlı token imzalama
 * kullanır. Bu sınıf ortak hash işlemlerini sağlar.
 *
 * @author Armağan Gökce
 */
class HashGenerator
{
    /**
     * HMAC-SHA256 hash oluşturur.
     *
     * @param string $data İmzalanacak veri
     * @param string $key  Gizli anahtar
     * @return string Hex formatında hash değeri
     */
    public static function hmacSha256(string $data, string $key): string
    {
        return hash_hmac('sha256', $data, $key);
    }

    /**
     * HMAC-SHA512 hash oluşturur.
     *
     * @param string $data İmzalanacak veri
     * @param string $key  Gizli anahtar
     * @return string Hex formatında hash değeri
     */
    public static function hmacSha512(string $data, string $key): string
    {
        return hash_hmac('sha512', $data, $key);
    }

    /**
     * HMAC-SHA256 hash oluşturup Base64 ile kodlar.
     *
     * PayTR gibi Base64 kodlanmış hash isteyen altyapılar için.
     *
     * @param string $data İmzalanacak veri
     * @param string $key  Gizli anahtar
     * @return string Base64 kodlanmış hash değeri
     */
    public static function hmacSha256Base64(string $data, string $key): string
    {
        return base64_encode(hash_hmac('sha256', $data, $key, true));
    }

    /**
     * SHA256 hash oluşturur (anahtarsız).
     *
     * @param string $data Hash'lenecek veri
     * @return string Hex formatında hash değeri
     */
    public static function sha256(string $data): string
    {
        return hash('sha256', $data);
    }

    /**
     * SHA1 hash oluşturur (anahtarsız).
     *
     * Bazı eski altyapılar SHA1 kullanır.
     *
     * @param string $data Hash'lenecek veri
     * @return string Hex formatında hash değeri
     */
    public static function sha1(string $data): string
    {
        return hash('sha1', $data);
    }

    /**
     * Base64 kodlama yapar.
     *
     * @param string $data Kodlanacak veri
     * @return string Base64 kodlanmış değer
     */
    public static function base64Encode(string $data): string
    {
        return base64_encode($data);
    }

    /**
     * Base64 kodlamasını çözer.
     *
     * @param string $data Çözümlenecek veri
     * @return string Çözümlenmiş değer
     */
    public static function base64Decode(string $data): string
    {
        $decoded = base64_decode($data, true);

        return $decoded !== false ? $decoded : '';
    }
}

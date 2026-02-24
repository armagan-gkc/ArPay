<?php

declare(strict_types=1);

namespace Arpay\DTO;

/**
 * 3D Secure callback veri nesnesi.
 *
 * Banka 3D doğrulama sayfasından dönen POST verilerini tutar.
 * Gateway'in completeSecurePayment() metoduna bu nesne verilir.
 *
 * Kullanım:
 * ```php
 * // Controller'da callback yakalama
 * $callbackData = SecureCallbackData::fromRequest($_POST);
 * $response = $gateway->completeSecurePayment($callbackData);
 * ```
 *
 * @author Armağan Gökce
 */
class SecureCallbackData
{
    /**
     * @param array<string, mixed> $data Banka dönüş verileri
     */
    public function __construct(
        protected readonly array $data,
    ) {
    }

    /**
     * POST verilerinden callback nesnesi oluşturur.
     *
     * Framework'e bağımsız: $_POST veya $request->all() kullanılabilir.
     *
     * @param array<string, mixed> $postData POST verileri ($_POST veya $request->all())
     */
    public static function fromRequest(array $postData): self
    {
        return new self(data: $postData);
    }

    /**
     * Tüm callback verilerini dizi olarak döndürür.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Belirli bir callback değerini döndürür.
     *
     * @param string $key     Anahtar adı
     * @param mixed  $default Varsayılan değer
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Anahtarın mevcut olup olmadığını kontrol eder.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }
}

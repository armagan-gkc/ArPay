<?php

declare(strict_types=1);

namespace Arpay\Http;

/**
 * HTTP yanıt sınıfı.
 *
 * Ödeme altyapısından gelen HTTP yanıtını temsil eder.
 * Durum kodu, gövde ve dizi dönüşümü sağlar.
 *
 * @author Armağan Gökce
 */
class HttpResponse
{
    /**
     * @param int $statusCode HTTP durum kodu
     * @param string $body Yanıt gövdesi (ham metin)
     * @param array<string, string> $headers Yanıt başlıkları
     */
    public function __construct(
        protected readonly int $statusCode,
        protected readonly string $body,
        protected readonly array $headers = [],
    ) {}

    /**
     * HTTP durum kodunu döndürür.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Ham yanıt gövdesini döndürür.
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Yanıt başlıklarını döndürür.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Yanıt gövdesini JSON olarak çözümleyip dizi döndürür.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * HTTP durum kodunun başarılı (2xx) olup olmadığını kontrol eder.
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}

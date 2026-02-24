<?php

declare(strict_types=1);

namespace Arpay\Http;

/**
 * HTTP istemci arayüzü.
 *
 * Tüm gateway'ler HTTP isteklerini bu arayüz üzerinden yapar.
 * Varsayılan olarak Guzzle implementasyonu kullanılır, ancak
 * istenirse farklı bir HTTP kütüphanesi de kullanılabilir.
 *
 * @author Armağan Gökce
 */
interface HttpClientInterface
{
    /**
     * HTTP POST isteği gönderir.
     *
     * @param string $url Hedef URL
     * @param array<string, string> $headers HTTP başlıkları
     * @param array<string, mixed>|string $body İstek gövdesi (dizi veya JSON string)
     *
     * @return HttpResponse Yanıt nesnesi
     */
    public function post(string $url, array $headers = [], array|string $body = []): HttpResponse;

    /**
     * HTTP GET isteği gönderir.
     *
     * @param string $url Hedef URL
     * @param array<string, string> $headers HTTP başlıkları
     * @param array<string, mixed> $query Sorgu parametreleri
     *
     * @return HttpResponse Yanıt nesnesi
     */
    public function get(string $url, array $headers = [], array $query = []): HttpResponse;
}

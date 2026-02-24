<?php

declare(strict_types=1);

namespace Arpay\Tests\Support;

use Arpay\Http\HttpClientInterface;
use Arpay\Http\HttpResponse;

/**
 * Test amaçlı sahte HTTP istemci.
 *
 * Her istek için sıradan yanıt döner. Gateway birim testlerinde
 * gerçek API çağrısı yapmadan gateway mantığını test etmeye yarar.
 */
class MockHttpClient implements HttpClientInterface
{
    /** @var HttpResponse[] Sıradaki yanıtlar */
    private array $responses = [];

    /** @var array<array{method: string, url: string, headers: array, body: mixed}> Yapılan istekler */
    private array $requests = [];

    /**
     * Sıradaki yanıtı belirler.
     */
    public function addResponse(int $statusCode = 200, array|string $body = [], array $headers = []): self
    {
        $bodyStr = is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR) : $body;
        $this->responses[] = new HttpResponse($statusCode, $bodyStr, $headers);

        return $this;
    }

    /**
     * Yapılan tüm istekleri döndürür.
     *
     * @return array<array{method: string, url: string, headers: array, body: mixed}>
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    /**
     * Son yapılan isteği döndürür.
     */
    public function getLastRequest(): ?array
    {
        return $this->requests[count($this->requests) - 1] ?? null;
    }

    public function post(string $url, array $headers = [], array|string $body = []): HttpResponse
    {
        $this->requests[] = [
            'method' => 'POST',
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        return array_shift($this->responses) ?? new HttpResponse(500, '{"error":"Yanıt tanımlanmadı"}');
    }

    public function get(string $url, array $headers = [], array $query = []): HttpResponse
    {
        $this->requests[] = [
            'method' => 'GET',
            'url' => $url,
            'headers' => $headers,
            'body' => $query,
        ];

        return array_shift($this->responses) ?? new HttpResponse(500, '{"error":"Yanıt tanımlanmadı"}');
    }
}

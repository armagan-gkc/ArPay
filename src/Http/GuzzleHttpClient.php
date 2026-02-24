<?php

declare(strict_types=1);

namespace Arpay\Http;

use Arpay\Exceptions\NetworkException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;

/**
 * Guzzle tabanlı HTTP istemci implementasyonu.
 *
 * Tüm ödeme altyapılarına yapılan HTTP istekleri bu sınıf
 * üzerinden gerçekleştirilir. Zaman aşımı, yeniden deneme
 * ve hata yakalama özellikleri içerir.
 *
 * @author Armağan Gökce
 */
class GuzzleHttpClient implements HttpClientInterface
{
    /**
     * Guzzle istemci örneği.
     */
    protected Client $client;

    /**
     * @param int $timeout İstek zaman aşımı süresi (saniye)
     * @param int $connectTimeout Bağlantı zaman aşımı süresi (saniye)
     * @param bool $verify SSL sertifika doğrulaması
     */
    public function __construct(
        int $timeout = 30,
        int $connectTimeout = 10,
        bool $verify = true,
    ) {
        $this->client = new Client([
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'verify' => $verify,
            'http_errors' => false, // HTTP hatalarında exception fırlatma, biz yöneteceğiz
        ]);
    }

    /**
     * @throws NetworkException Bağlantı hatası veya zaman aşımı durumunda
     */
    public function post(string $url, array $headers = [], array|string $body = []): HttpResponse
    {
        try {
            $options = ['headers' => $headers];

            if (is_string($body)) {
                // Ham JSON string gönderimi
                $options['body'] = $body;
            } elseif ($this->isJsonRequest($headers)) {
                // JSON gövde olarak gönder
                $options['json'] = $body;
            } else {
                // Form verisi olarak gönder
                $options['form_params'] = $body;
            }

            $response = $this->client->post($url, $options);

            return new HttpResponse(
                statusCode: $response->getStatusCode(),
                body: (string) $response->getBody(),
                headers: $this->flattenHeaders($response->getHeaders()),
            );
        } catch (ConnectException $e) {
            throw new NetworkException(
                "Bağlantı kurulamadı: {$e->getMessage()}",
                0,
                $e,
            );
        } catch (RequestException $e) {
            throw new NetworkException(
                "İstek hatası: {$e->getMessage()}",
                $e->getCode(),
                $e,
            );
        } catch (TransferException $e) {
            throw new NetworkException(
                "Transfer hatası: {$e->getMessage()}",
                $e->getCode(),
                $e,
            );
        }
    }

    /**
     * @throws NetworkException Bağlantı hatası veya zaman aşımı durumunda
     */
    public function get(string $url, array $headers = [], array $query = []): HttpResponse
    {
        try {
            $response = $this->client->get($url, [
                'headers' => $headers,
                'query' => $query,
            ]);

            return new HttpResponse(
                statusCode: $response->getStatusCode(),
                body: (string) $response->getBody(),
                headers: $this->flattenHeaders($response->getHeaders()),
            );
        } catch (ConnectException $e) {
            throw new NetworkException(
                "Bağlantı kurulamadı: {$e->getMessage()}",
                0,
                $e,
            );
        } catch (TransferException $e) {
            throw new NetworkException(
                "Transfer hatası: {$e->getMessage()}",
                $e->getCode(),
                $e,
            );
        }
    }

    /**
     * İstek başlıklarının JSON content-type içerip içermediğini kontrol eder.
     *
     * @param array<string, string> $headers
     */
    protected function isJsonRequest(array $headers): bool
    {
        foreach ($headers as $key => $value) {
            if ('content-type' === strtolower($key) && str_contains(strtolower($value), 'json')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Guzzle'ın çok değerli başlıklarını tek değerli formata dönüştürür.
     *
     * @param array<string, string[]> $headers Guzzle başlık formatı
     *
     * @return array<string, string> Düzleştirilmiş başlıklar
     */
    protected function flattenHeaders(array $headers): array
    {
        $flat = [];
        foreach ($headers as $name => $values) {
            $flat[$name] = implode(', ', $values);
        }

        return $flat;
    }
}

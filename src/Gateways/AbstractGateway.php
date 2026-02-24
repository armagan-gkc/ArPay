<?php

declare(strict_types=1);

namespace Arpay\Gateways;

use Arpay\Contracts\GatewayInterface;
use Arpay\Exceptions\UnsupportedOperationException;
use Arpay\Http\GuzzleHttpClient;
use Arpay\Http\HttpClientInterface;
use Arpay\Support\Config;

/**
 * Tüm gateway implementasyonlarının temel sınıfı.
 *
 * Ortak yapılandırma, HTTP istemci yönetimi, test modu ve
 * yetenek kontrolü işlevlerini sağlar. Her gateway bu sınıfı
 * extend eder ve kendi özel mantığını ekler.
 *
 * @author Armağan Gökce
 */
abstract class AbstractGateway implements GatewayInterface
{
    /**
     * Gateway yapılandırması.
     */
    protected Config $config;

    /**
     * HTTP istemci örneği.
     */
    protected HttpClientInterface $httpClient;

    /**
     * Test (sandbox) modu aktif mi?
     */
    protected bool $testMode = false;

    public function __construct()
    {
        $this->config = new Config();
        $this->httpClient = new GuzzleHttpClient();
    }

    /**
     * {@inheritdoc}
     */
    public function configure(Config $config): static
    {
        $this->config = $config;

        /* Test modu config'den de ayarlanabilir */
        if ($config->has('test_mode')) {
            $this->testMode = (bool) $config->get('test_mode');
        }

        /* Zorunlu alanları kontrol et */
        $this->config->validateRequired($this->getRequiredConfigKeys());

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTestMode(bool $active = true): static
    {
        $this->testMode = $active;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * HTTP istemcisini değiştirir.
     *
     * Test veya özel HTTP istemci kullanımı için.
     */
    public function setHttpClient(HttpClientInterface $client): static
    {
        $this->httpClient = $client;

        return $this;
    }

    /**
     * Mevcut yapılandırmayı döndürür.
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Gateway'in zorunlu yapılandırma anahtarlarını döndürür.
     *
     * Her gateway kendi zorunlu alanlarını bu metotla tanımlar.
     *
     * @return string[] Zorunlu yapılandırma anahtarları
     */
    abstract protected function getRequiredConfigKeys(): array;

    /**
     * Canlı (production) API URL'sini döndürür.
     */
    abstract protected function getBaseUrl(): string;

    /**
     * Test (sandbox) API URL'sini döndürür.
     */
    abstract protected function getTestBaseUrl(): string;

    /**
     * Aktif olan API URL'sini döndürür (test/canlı durumuna göre).
     */
    protected function getActiveBaseUrl(): string
    {
        return $this->testMode ? $this->getTestBaseUrl() : $this->getBaseUrl();
    }

    /**
     * Gateway'in belirli bir özelliği desteklediğini kontrol eder.
     *
     * Desteklemiyorsa UnsupportedOperationException fırlatır.
     *
     * @param string $feature Kontrol edilecek özellik adı
     * @throws UnsupportedOperationException Özellik desteklenmiyorsa
     */
    protected function ensureSupports(string $feature): void
    {
        if (!in_array($feature, $this->getSupportedFeatures(), true)) {
            throw new UnsupportedOperationException($this->getName(), $feature);
        }
    }
}

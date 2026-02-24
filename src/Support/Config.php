<?php

declare(strict_types=1);

namespace Arpay\Support;

use Arpay\Exceptions\InvalidParameterException;

/**
 * Gateway yapılandırma yönetim sınıfı.
 *
 * API anahtarları, merchant bilgileri ve diğer yapılandırma
 * değerlerini tutar. Dizi tabanlı erişim sağlar.
 *
 * Kullanım:
 * ```php
 * $config = new Config([
 *     'merchant_id'   => '123456',
 *     'merchant_key'  => 'XXXXX',
 *     'merchant_salt' => 'YYYYY',
 *     'test_mode'     => true,
 * ]);
 *
 * echo $config->get('merchant_id'); // '123456'
 * echo $config->merchant_id;       // '123456' (magic erişim)
 * ```
 *
 * @author Armağan Gökce
 */
class Config
{
    /**
     * Yapılandırma değerleri deposu.
     *
     * @var array<string, mixed>
     */
    protected array $items = [];

    /**
     * @param array<string, mixed> $items Yapılandırma anahtar-değer çiftleri
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Yapılandırma değerini döndürür.
     *
     * @param string $key     Anahtar adı
     * @param mixed  $default Değer bulunamazsa döndürülecek varsayılan
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    /**
     * Yapılandırma değerini ayarlar.
     */
    public function set(string $key, mixed $value): static
    {
        $this->items[$key] = $value;

        return $this;
    }

    /**
     * Anahtarın mevcut olup olmadığını kontrol eder.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Tüm yapılandırma değerlerini dizi olarak döndürür.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Zorunlu alanların mevcut olduğunu doğrular.
     *
     * Eksik alan varsa InvalidParameterException fırlatır.
     *
     * @param string[] $requiredKeys Zorunlu alan adları
     * @throws InvalidParameterException Eksik veya boş alan varsa
     */
    public function validateRequired(array $requiredKeys): void
    {
        foreach ($requiredKeys as $key) {
            if (!$this->has($key) || $this->get($key) === '' || $this->get($key) === null) {
                throw new InvalidParameterException(
                    $key,
                    "Bu yapılandırma alanı zorunludur."
                );
            }
        }
    }

    /**
     * Magic getter — $config->merchant_id şeklinde erişim sağlar.
     */
    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    /**
     * Magic setter — $config->merchant_id = 'xxx' şeklinde atama sağlar.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->set($name, $value);
    }

    /**
     * Magic isset — isset($config->merchant_id) kontrolü sağlar.
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }
}

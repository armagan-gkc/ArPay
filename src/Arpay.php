<?php

declare(strict_types=1);

namespace Arpay;

use Arpay\Contracts\GatewayInterface;
use Arpay\Exceptions\GatewayNotFoundException;
use Arpay\Exceptions\InvalidParameterException;
use Arpay\Support\Config;

/**
 * Arpay — Türk Ödeme Altyapıları için Birleşik PHP Kütüphanesi.
 *
 * Bu sınıf kütüphanenin ana giriş noktasıdır (Facade).
 * Tek bir metotla istediğiniz ödeme altyapısını oluşturabilirsiniz.
 *
 * Kullanım:
 * ```php
 * use Arpay\Arpay;
 *
 * // PayTR ile ödeme
 * $gateway = Arpay::create('paytr', [
 *     'merchant_id'   => '123456',
 *     'merchant_key'  => 'XXXXX',
 *     'merchant_salt' => 'YYYYY',
 *     'test_mode'     => true,
 * ]);
 *
 * // Gateway değişikliği — sadece bu satırı değiştirin:
 * $gateway = Arpay::create('iyzico', [
 *     'api_key'    => 'XXXXX',
 *     'secret_key' => 'YYYYY',
 * ]);
 * ```
 *
 * @author Armağan Gökce
 * @license MIT
 *
 * @see https://github.com/armagangokce/arpay
 */
class Arpay
{
    /**
     * Kütüphane sürüm numarası.
     */
    public const VERSION = '1.0.0';

    /**
     * Yeni bir ödeme gateway'i oluşturur ve yapılandırır.
     *
     * Bu metot kütüphanenin tek giriş noktasıdır. Gateway adını
     * ve yapılandırma değerlerini vererek kullanıma hazır bir
     * gateway nesnesi elde edersiniz.
     *
     * @param string $gateway Gateway kısa adı ('paytr', 'iyzico', 'vepara', vb.)
     * @param array<string, mixed> $config API anahtarları ve diğer yapılandırma değerleri
     *
     * @return GatewayInterface Yapılandırılmış gateway nesnesi
     *
     * @throws GatewayNotFoundException Geçersiz gateway adı
     * @throws InvalidParameterException Eksik yapılandırma
     */
    public static function create(string $gateway, array $config = []): GatewayInterface
    {
        $instance = ArpayFactory::create($gateway);

        if (!empty($config)) {
            $instance->configure(new Config($config));
        }

        return $instance;
    }

    /**
     * Desteklenen tüm gateway adlarını döndürür.
     *
     * @return string[] Kullanılabilir gateway kısa adları
     */
    public static function getAvailableGateways(): array
    {
        return ArpayFactory::getAvailableGateways();
    }

    /**
     * Kütüphane sürüm numarasını döndürür.
     */
    public static function version(): string
    {
        return self::VERSION;
    }
}

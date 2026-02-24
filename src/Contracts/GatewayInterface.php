<?php

declare(strict_types=1);

namespace Arpay\Contracts;

use Arpay\Support\Config;

/**
 * Tüm ödeme altyapılarının (gateway) uyması gereken ana arayüz.
 *
 * Her gateway bu arayüzü implement etmelidir. Gateway'in adı,
 * yapılandırması ve desteklenen özellikleri bu arayüz üzerinden yönetilir.
 *
 * @author Armağan Gökce
 */
interface GatewayInterface
{
    /**
     * Gateway'in görünen adını döndürür.
     *
     * Örnek: "PayTR", "Iyzico"
     */
    public function getName(): string;

    /**
     * Gateway'in kısa kodunu döndürür.
     *
     * Örnek: "paytr", "iyzico"
     */
    public function getShortName(): string;

    /**
     * Gateway yapılandırmasını uygular.
     *
     * @param Config $config API anahtarları ve diğer yapılandırma değerleri
     *
     * @return static Zincirleme kullanım için kendini döndürür
     */
    public function configure(Config $config): static;

    /**
     * Bu gateway'in desteklediği özellikleri döndürür.
     *
     * @return string[] Desteklenen özellik listesi (örn: ['pay', 'refund', '3dsecure'])
     */
    public function getSupportedFeatures(): array;

    /**
     * Test (sandbox) modunu açar veya kapatır.
     *
     * @param bool $active true ise test modu aktif, false ise canlı mod
     *
     * @return static Zincirleme kullanım için kendini döndürür
     */
    public function setTestMode(bool $active = true): static;

    /**
     * Gateway'in test modunda olup olmadığını döndürür.
     */
    public function isTestMode(): bool;
}

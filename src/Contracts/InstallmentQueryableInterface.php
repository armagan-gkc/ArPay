<?php

declare(strict_types=1);

namespace Arpay\Contracts;

use Arpay\DTO\InstallmentInfo;

/**
 * Taksit oranı sorgulama yeteneği arayüzü.
 *
 * Kart BIN numarasına göre taksit seçeneklerini
 * sorgulayabilen gateway'ler bu arayüzü implement eder.
 *
 * @author Armağan Gökce
 */
interface InstallmentQueryableInterface
{
    /**
     * Kart BIN numarasına göre taksit seçeneklerini sorgular.
     *
     * @param string $binNumber Kart numarasının ilk 6-8 hanesi
     * @param float  $amount    Toplam tutar (TL cinsinden)
     * @return InstallmentInfo[] Taksit seçenekleri listesi
     */
    public function queryInstallments(string $binNumber, float $amount): array;
}

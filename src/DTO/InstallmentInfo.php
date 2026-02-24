<?php

declare(strict_types=1);

namespace Arpay\DTO;

/**
 * Taksit bilgisi veri nesnesi.
 *
 * Belirli bir taksit seçeneğinin detaylarını tutar.
 * queryInstallments() sonucunda döner.
 *
 * @author Armağan Gökce
 */
class InstallmentInfo
{
    public function __construct(
        public readonly int $installmentCount,
        public readonly float $installmentAmount,
        public readonly float $totalAmount,
        public readonly float $interestRate = 0.0,
    ) {}

    /**
     * Yeni taksit bilgisi oluşturur.
     *
     * @param int $count Taksit sayısı
     * @param float $perInstallment Taksit başına tutar
     * @param float $total Toplam tutar (faiz dahil)
     * @param float $interestRate Faiz oranı (yüzde)
     */
    public static function create(
        int $count,
        float $perInstallment,
        float $total,
        float $interestRate = 0.0,
    ): self {
        return new self(
            installmentCount: $count,
            installmentAmount: $perInstallment,
            totalAmount: $total,
            interestRate: $interestRate,
        );
    }
}

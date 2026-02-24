<?php

declare(strict_types=1);

namespace Arpay\Contracts;

use Arpay\DTO\PaymentRequest;
use Arpay\DTO\PaymentResponse;

/**
 * Ödeme yapabilme yeteneği arayüzü.
 *
 * Tek çekim ve taksitli ödeme işlemlerini destekleyen
 * gateway'ler bu arayüzü implement eder.
 *
 * @author Armağan Gökce
 */
interface PayableInterface
{
    /**
     * Tek çekim ödeme yapar.
     *
     * @param PaymentRequest $request Ödeme istek bilgileri
     * @return PaymentResponse Ödeme sonucu
     */
    public function pay(PaymentRequest $request): PaymentResponse;

    /**
     * Taksitli ödeme yapar.
     *
     * Taksit sayısı PaymentRequest içindeki installmentCount alanından alınır.
     *
     * @param PaymentRequest $request Ödeme istek bilgileri (taksit sayısı dahil)
     * @return PaymentResponse Ödeme sonucu
     */
    public function payInstallment(PaymentRequest $request): PaymentResponse;
}

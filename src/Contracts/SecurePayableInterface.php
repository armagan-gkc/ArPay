<?php

declare(strict_types=1);

namespace Arpay\Contracts;

use Arpay\DTO\PaymentResponse;
use Arpay\DTO\SecureCallbackData;
use Arpay\DTO\SecureInitResponse;
use Arpay\DTO\SecurePaymentRequest;

/**
 * 3D Secure ödeme yeteneği arayüzü.
 *
 * 3D Secure doğrulamalı ödeme işlemlerini destekleyen
 * gateway'ler bu arayüzü implement eder.
 *
 * 3D Secure akışı iki adımdan oluşur:
 * 1. initSecurePayment() — Ödemeyi başlatır, banka yönlendirme bilgisi döner
 * 2. completeSecurePayment() — Banka dönüşünü alır, ödemeyi tamamlar
 *
 * @author Armağan Gökce
 */
interface SecurePayableInterface
{
    /**
     * 3D Secure ödeme başlatır.
     *
     * Dönen SecureInitResponse nesnesi, müşteriyi banka sayfasına
     * yönlendirmek için gerekli HTML formu veya URL'yi içerir.
     *
     * @param SecurePaymentRequest $request 3D Secure ödeme istek bilgileri
     * @return SecureInitResponse Yönlendirme bilgileri
     */
    public function initSecurePayment(SecurePaymentRequest $request): SecureInitResponse;

    /**
     * 3D Secure callback'ini işler ve ödemeyi tamamlar.
     *
     * Banka yönlendirmesinden dönen POST verileri ile
     * ödeme doğrulamasını ve tahsilatı gerçekleştirir.
     *
     * @param SecureCallbackData $data Banka dönüş verileri
     * @return PaymentResponse Ödeme sonucu
     */
    public function completeSecurePayment(SecureCallbackData $data): PaymentResponse;
}

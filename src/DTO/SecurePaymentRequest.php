<?php

declare(strict_types=1);

namespace Arpay\DTO;

/**
 * 3D Secure ödeme talebi veri nesnesi.
 *
 * Normal ödeme talebine ek olarak callback URL bilgisini içerir.
 * 3D Secure akışında banka yönlendirmesinden sonra müşterinin
 * geri döneceği URL bu nesnede tanımlanır.
 *
 * Kullanım:
 * ```php
 * $request = SecurePaymentRequest::create()
 *     ->amount(500.00)
 *     ->orderId('SIP-003')
 *     ->card($card)
 *     ->callbackUrl('https://sitem.com/odeme/callback');
 * ```
 *
 * @author Armağan Gökce
 */
class SecurePaymentRequest extends PaymentRequest
{
    /** @var string Banka dönüş URL'si */
    protected string $callbackUrl = '';

    /** @var string Başarılı ödeme sonrası yönlendirme URL'si */
    protected string $successUrl = '';

    /** @var string Başarısız ödeme sonrası yönlendirme URL'si */
    protected string $failUrl = '';

    /**
     * Banka dönüş URL'sini ayarlar.
     *
     * 3D doğrulama tamamlandıktan sonra bankanın yönlendireceği
     * callback URL. Bu URL'ye POST verisi gönderilir.
     */
    public function callbackUrl(string $url): static
    {
        $this->callbackUrl = $url;

        return $this;
    }

    /**
     * Başarılı ödeme sonrası yönlendirme URL'sini ayarlar.
     */
    public function successUrl(string $url): static
    {
        $this->successUrl = $url;

        return $this;
    }

    /**
     * Başarısız ödeme sonrası yönlendirme URL'sini ayarlar.
     */
    public function failUrl(string $url): static
    {
        $this->failUrl = $url;

        return $this;
    }

    public function getCallbackUrl(): string
    {
        return $this->callbackUrl;
    }

    public function getSuccessUrl(): string
    {
        return $this->successUrl;
    }

    public function getFailUrl(): string
    {
        return $this->failUrl;
    }
}

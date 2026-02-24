<?php

declare(strict_types=1);

namespace Arpay\Exceptions;

/**
 * Ödeme başarısızlık hatası.
 *
 * Ödeme altyapısı tarafından ödeme reddedildiğinde fırlatılır.
 * Gateway'den dönen hata kodu, mesaj ve ham yanıt bilgilerine erişilebilir.
 *
 * NOT: Bu hata sadece kritik başarısızlıklarda fırlatılır.
 * Normal reddedilmeler (yetersiz bakiye vb.) PaymentResponse üzerinden
 * isSuccessful() === false şeklinde döner.
 *
 * @author Armağan Gökce
 */
class PaymentFailedException extends ArpayException
{
    /**
     * @param string $errorCode Gateway hata kodu
     * @param string $errorMessage Hata mesajı
     * @param array<string, mixed> $rawResponse Gateway'den gelen ham yanıt
     */
    public function __construct(
        protected string $errorCode = '',
        string $errorMessage = 'Ödeme işlemi başarısız',
        protected array $rawResponse = [],
    ) {
        parent::__construct($errorMessage);
    }

    /**
     * Gateway'in hata kodunu döndürür.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Gateway'den gelen ham yanıtı döndürür.
     *
     * @return array<string, mixed>
     */
    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }
}

<?php

declare(strict_types=1);

namespace Arpay\Exceptions;

/**
 * Ağ / HTTP iletişim hatası.
 *
 * Ödeme altyapısına ulaşılamadığında, bağlantı zaman aşımına
 * uğradığında veya HTTP hatası alındığında fırlatılır.
 *
 * @author Armağan Gökce
 */
class NetworkException extends ArpayException
{
    /**
     * @param string $message Hata açıklaması
     * @param int $code HTTP durum kodu (varsa)
     * @param null|\Throwable $previous Önceki hata
     */
    public function __construct(string $message = 'Ağ hatası oluştu', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Ağ hatası: {$message}", $code, $previous);
    }
}

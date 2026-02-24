<?php

declare(strict_types=1);

namespace Arpay\Exceptions;

/**
 * Gateway bulunamadığında fırlatılır.
 *
 * Geçersiz veya tanınmayan bir gateway adı verildiğinde
 * bu hata oluşur.
 *
 * @author Armağan Gökce
 */
class GatewayNotFoundException extends ArpayException
{
    /**
     * @param string $gatewayName Bulunamayan gateway adı
     */
    public function __construct(string $gatewayName)
    {
        parent::__construct("Gateway bulunamadı: '{$gatewayName}'");
    }
}

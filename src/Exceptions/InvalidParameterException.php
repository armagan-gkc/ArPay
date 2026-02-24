<?php

declare(strict_types=1);

namespace Arpay\Exceptions;

/**
 * Geçersiz veya eksik parametre hatası.
 *
 * Zorunlu bir parametre eksik olduğunda veya geçersiz bir
 * değer verildiğinde fırlatılır.
 *
 * @author Armağan Gökce
 */
class InvalidParameterException extends ArpayException
{
    /**
     * @param string $field Hatalı alanın adı
     * @param string $message Opsiyonel detay mesajı
     */
    public function __construct(string $field, string $message = '')
    {
        $msg = "Geçersiz parametre: '{$field}'";
        if ('' !== $message) {
            $msg .= " — {$message}";
        }
        parent::__construct($msg);
    }
}

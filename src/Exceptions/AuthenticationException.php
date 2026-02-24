<?php

declare(strict_types=1);

namespace Arpay\Exceptions;

/**
 * Kimlik doğrulama hatası.
 *
 * API anahtarları geçersiz olduğunda veya hash doğrulaması
 * başarısız olduğunda fırlatılır.
 *
 * @author Armağan Gökce
 */
class AuthenticationException extends ArpayException
{
    public function __construct(string $message = 'Kimlik doğrulama başarısız')
    {
        parent::__construct("Kimlik doğrulama hatası: {$message}");
    }
}

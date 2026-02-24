<?php

declare(strict_types=1);

namespace Arpay\Exceptions;

/**
 * Desteklenmeyen işlem hatası.
 *
 * Bir gateway'in desteklemediği bir özellik çağrıldığında fırlatılır.
 * Örneğin Papara, 3D Secure desteklemediğinden initSecurePayment()
 * çağrıldığında bu hata oluşur.
 *
 * @author Armağan Gökce
 */
class UnsupportedOperationException extends ArpayException
{
    /**
     * @param string $gateway Gateway adı
     * @param string $operation İstenen işlem adı
     */
    public function __construct(string $gateway, string $operation)
    {
        parent::__construct("'{$gateway}' gateway'i '{$operation}' işlemini desteklemiyor.");
    }
}

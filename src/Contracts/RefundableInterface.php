<?php

declare(strict_types=1);

namespace Arpay\Contracts;

use Arpay\DTO\RefundRequest;
use Arpay\DTO\RefundResponse;

/**
 * İade yapabilme yeteneği arayüzü.
 *
 * Tam veya kısmi iade işlemlerini destekleyen
 * gateway'ler bu arayüzü implement eder.
 *
 * @author Armağan Gökce
 */
interface RefundableInterface
{
    /**
     * Tam veya kısmi iade yapar.
     *
     * Kısmi iade için RefundRequest içinde tutarı belirtin.
     * Tam iade için tutarı orijinal tutar ile aynı yapın.
     *
     * @param RefundRequest $request İade istek bilgileri
     * @return RefundResponse İade sonucu
     */
    public function refund(RefundRequest $request): RefundResponse;
}

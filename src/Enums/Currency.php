<?php

declare(strict_types=1);

namespace Arpay\Enums;

/**
 * Desteklenen para birimleri.
 *
 * @author Armağan Gökce
 */
enum Currency: string
{
    /** Türk Lirası */
    case TRY = 'TRY';

    /** Amerikan Doları */
    case USD = 'USD';

    /** Euro */
    case EUR = 'EUR';

    /** İngiliz Sterlini */
    case GBP = 'GBP';
}

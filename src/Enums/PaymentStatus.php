<?php

declare(strict_types=1);

namespace Arpay\Enums;

/**
 * Ödeme durumu değerleri.
 *
 * Bir ödeme işleminin mevcut durumunu temsil eder.
 *
 * @author Armağan Gökce
 */
enum PaymentStatus: string
{
    /** Ödeme başarıyla tamamlandı */
    case Successful = 'successful';

    /** Ödeme başarısız oldu */
    case Failed = 'failed';

    /** Ödeme beklemede */
    case Pending = 'pending';

    /** Ödeme iptal edildi */
    case Cancelled = 'cancelled';

    /** Ödeme iade edildi */
    case Refunded = 'refunded';
}

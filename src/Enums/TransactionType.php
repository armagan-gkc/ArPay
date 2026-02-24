<?php

declare(strict_types=1);

namespace Arpay\Enums;

/**
 * İşlem tipi değerleri.
 *
 * Yapılan ödeme işleminin türünü belirtir.
 *
 * @author Armağan Gökce
 */
enum TransactionType: string
{
    /** Tek çekim ödeme */
    case Single = 'single';

    /** Taksitli ödeme */
    case Installment = 'installment';

    /** İade işlemi */
    case Refund = 'refund';

    /** 3D Secure ödeme */
    case Secure3D = 'secure3d';

    /** Abonelik / tekrarlayan ödeme */
    case Subscription = 'subscription';

    /** Ön provizyon (pre-auth) */
    case PreAuth = 'preauth';
}

<?php

declare(strict_types=1);

namespace Arpay\Enums;

/**
 * Desteklenen ödeme altyapıları.
 *
 * Her bir sanal pos / ödeme altyapısı için tanımlanmış sabit değerler.
 *
 * @author Armağan Gökce
 */
enum Gateway: string
{
    /** PayTR Sanal POS */
    case PayTR = 'paytr';

    /** Iyzico Ödeme Altyapısı */
    case Iyzico = 'iyzico';

    /** Vepara Ödeme Altyapısı */
    case Vepara = 'vepara';

    /** ParamPos Sanal POS */
    case ParamPos = 'parampos';

    /** iPara Ödeme Altyapısı */
    case Ipara = 'ipara';

    /** Ödeal Ödeme Altyapısı */
    case Odeal = 'odeal';

    /** Paynet Ödeme Altyapısı */
    case Paynet = 'paynet';

    /** PayU Ödeme Altyapısı */
    case PayU = 'payu';

    /** Papara Sanal POS */
    case Papara = 'papara';

    /**
     * Verilen kısa addan gateway enum değerini döndürür.
     *
     * @throws \ValueError Gateway bulunamazsa fırlatılır
     */
    public static function fromShortName(string $name): self
    {
        return self::from(strtolower(trim($name)));
    }

    /**
     * Gateway'in görünen adını döndürür.
     */
    public function displayName(): string
    {
        return match ($this) {
            self::PayTR => 'PayTR',
            self::Iyzico => 'Iyzico',
            self::Vepara => 'Vepara',
            self::ParamPos => 'ParamPos',
            self::Ipara => 'iPara',
            self::Odeal => 'Ödeal',
            self::Paynet => 'Paynet',
            self::PayU => 'PayU',
            self::Papara => 'Papara',
        };
    }
}

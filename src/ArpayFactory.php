<?php

declare(strict_types=1);

namespace Arpay;

use Arpay\Contracts\GatewayInterface;
use Arpay\Enums\Gateway;
use Arpay\Exceptions\GatewayNotFoundException;
use Arpay\Gateways\Ipara\IparaGateway;
use Arpay\Gateways\Iyzico\IyzicoGateway;
use Arpay\Gateways\Odeal\OdealGateway;
use Arpay\Gateways\Papara\PaparaGateway;
use Arpay\Gateways\ParamPos\ParamPosGateway;
use Arpay\Gateways\PayNet\PaynetGateway;
use Arpay\Gateways\PayTR\PayTRGateway;
use Arpay\Gateways\PayU\PayUGateway;
use Arpay\Gateways\Vepara\VeparaGateway;

/**
 * Gateway fabrika sınıfı.
 *
 * Verilen gateway adına göre ilgili gateway nesnesini oluşturur.
 * Arpay facade sınıfı tarafından dahili olarak kullanılır.
 *
 * @author Armağan Gökce
 */
class ArpayFactory
{
    /**
     * Verilen gateway adına göre gateway nesnesi oluşturur.
     *
     * @param string $gatewayName Gateway kısa adı (örn: 'paytr', 'iyzico')
     *
     * @return GatewayInterface Oluşturulan gateway nesnesi
     *
     * @throws GatewayNotFoundException Geçersiz gateway adı verildiğinde
     */
    public static function create(string $gatewayName): GatewayInterface
    {
        try {
            $gateway = Gateway::fromShortName($gatewayName);
        } catch (\ValueError) {
            throw new GatewayNotFoundException($gatewayName);
        }

        return match ($gateway) {
            Gateway::PayTR => new PayTRGateway(),
            Gateway::Iyzico => new IyzicoGateway(),
            Gateway::Vepara => new VeparaGateway(),
            Gateway::ParamPos => new ParamPosGateway(),
            Gateway::Ipara => new IparaGateway(),
            Gateway::Odeal => new OdealGateway(),
            Gateway::Paynet => new PaynetGateway(),
            Gateway::PayU => new PayUGateway(),
            Gateway::Papara => new PaparaGateway(),
        };
    }

    /**
     * Desteklenen tüm gateway isimlerini döndürür.
     *
     * @return string[] Gateway kısa adları
     */
    public static function getAvailableGateways(): array
    {
        return array_map(
            fn (Gateway $g) => $g->value,
            Gateway::cases(),
        );
    }
}

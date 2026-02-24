<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\Arpay;
use Arpay\ArpayFactory;
use Arpay\Contracts\GatewayInterface;
use Arpay\Contracts\InstallmentQueryableInterface;
use Arpay\Contracts\PayableInterface;
use Arpay\Contracts\RefundableInterface;
use Arpay\Contracts\SecurePayableInterface;
use Arpay\Contracts\SubscribableInterface;
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
use PHPUnit\Framework\TestCase;

/**
 * Arpay Facade ve ArpayFactory birim testleri.
 *
 * @internal
 *
 * @coversNothing
 */
class ArpayTest extends TestCase
{
    public function test_version(): void
    {
        $this->assertSame('1.0.0', Arpay::version());
    }

    public function test_get_available_gateways_returns_nine(): void
    {
        $gateways = Arpay::getAvailableGateways();
        $this->assertCount(9, $gateways);
        $this->assertContains('paytr', $gateways);
        $this->assertContains('iyzico', $gateways);
        $this->assertContains('vepara', $gateways);
        $this->assertContains('parampos', $gateways);
        $this->assertContains('ipara', $gateways);
        $this->assertContains('odeal', $gateways);
        $this->assertContains('paynet', $gateways);
        $this->assertContains('payu', $gateways);
        $this->assertContains('papara', $gateways);
    }

    /**
     * @dataProvider gatewayClassProvider
     */
    public function test_factory_creates_correct_class(string $name, string $expectedClass): void
    {
        $gateway = ArpayFactory::create($name);
        $this->assertInstanceOf($expectedClass, $gateway);
        $this->assertInstanceOf(GatewayInterface::class, $gateway);
    }

    public static function gatewayClassProvider(): array
    {
        return [
            ['paytr', PayTRGateway::class],
            ['iyzico', IyzicoGateway::class],
            ['vepara', VeparaGateway::class],
            ['parampos', ParamPosGateway::class],
            ['ipara', IparaGateway::class],
            ['odeal', OdealGateway::class],
            ['paynet', PaynetGateway::class],
            ['payu', PayUGateway::class],
            ['papara', PaparaGateway::class],
        ];
    }

    public function test_factory_throws_for_unknown_gateway(): void
    {
        $this->expectException(GatewayNotFoundException::class);
        ArpayFactory::create('nonexistent');
    }

    public function test_create_with_config_sets_test_mode(): void
    {
        $gateway = Arpay::create('paytr', [
            'merchant_id' => '123456',
            'merchant_key' => 'XXXXX',
            'merchant_salt' => 'YYYYY',
            'test_mode' => true,
        ]);

        $this->assertTrue($gateway->isTestMode());
    }

    public function test_paytr_implements_all_interfaces(): void
    {
        $gateway = ArpayFactory::create('paytr');
        $this->assertInstanceOf(PayableInterface::class, $gateway);
        $this->assertInstanceOf(RefundableInterface::class, $gateway);
        $this->assertInstanceOf(SecurePayableInterface::class, $gateway);
        $this->assertInstanceOf(SubscribableInterface::class, $gateway);
        $this->assertInstanceOf(InstallmentQueryableInterface::class, $gateway);
    }

    public function test_iyzico_implements_all_interfaces(): void
    {
        $gateway = ArpayFactory::create('iyzico');
        $this->assertInstanceOf(PayableInterface::class, $gateway);
        $this->assertInstanceOf(RefundableInterface::class, $gateway);
        $this->assertInstanceOf(SecurePayableInterface::class, $gateway);
        $this->assertInstanceOf(SubscribableInterface::class, $gateway);
        $this->assertInstanceOf(InstallmentQueryableInterface::class, $gateway);
    }

    public function test_papara_does_not_implement_secure_or_subscription(): void
    {
        $gateway = ArpayFactory::create('papara');
        $this->assertInstanceOf(PayableInterface::class, $gateway);
        $this->assertInstanceOf(RefundableInterface::class, $gateway);
        $this->assertNotInstanceOf(SecurePayableInterface::class, $gateway);
        $this->assertNotInstanceOf(SubscribableInterface::class, $gateway);
    }

    public function test_odeal_does_not_implement_subscription(): void
    {
        $gateway = ArpayFactory::create('odeal');
        $this->assertInstanceOf(PayableInterface::class, $gateway);
        $this->assertInstanceOf(SecurePayableInterface::class, $gateway);
        $this->assertNotInstanceOf(SubscribableInterface::class, $gateway);
        $this->assertNotInstanceOf(InstallmentQueryableInterface::class, $gateway);
    }

    public function test_set_test_mode_fluent(): void
    {
        $gateway = ArpayFactory::create('iyzico');
        $result = $gateway->setTestMode(true);

        $this->assertSame($gateway, $result);
        $this->assertTrue($gateway->isTestMode());
    }
}

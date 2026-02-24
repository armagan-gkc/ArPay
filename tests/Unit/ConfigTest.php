<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\Exceptions\InvalidParameterException;
use Arpay\Support\Config;
use PHPUnit\Framework\TestCase;

/**
 * Config sınıfı birim testleri.
 *
 * @internal
 *
 * @coversNothing
 */
class ConfigTest extends TestCase
{
    public function test_get_and_set(): void
    {
        $config = new Config(['key1' => 'value1']);

        $this->assertSame('value1', $config->get('key1'));
        $this->assertNull($config->get('nonexistent'));
        $this->assertSame('default', $config->get('nonexistent', 'default'));
    }

    public function test_set_value(): void
    {
        $config = new Config();
        $config->set('api_key', 'test123');

        $this->assertSame('test123', $config->get('api_key'));
    }

    public function test_has(): void
    {
        $config = new Config(['existing' => 'yes']);

        $this->assertTrue($config->has('existing'));
        $this->assertFalse($config->has('missing'));
    }

    public function test_magic_get_set(): void
    {
        $config = new Config();
        $config->merchant_id = 'M123';

        $this->assertSame('M123', $config->merchant_id);
    }

    public function test_to_array(): void
    {
        $data = ['key1' => 'val1', 'key2' => 'val2'];
        $config = new Config($data);

        $this->assertSame($data, $config->toArray());
    }

    public function test_validate_required_passes(): void
    {
        $config = new Config(['api_key' => 'xxx', 'secret' => 'yyy']);
        $config->validateRequired(['api_key', 'secret']);

        // Hata fırlatılmazsa test başarılı
        $this->assertTrue(true);
    }

    public function test_validate_required_throws_on_missing(): void
    {
        $this->expectException(InvalidParameterException::class);

        $config = new Config(['api_key' => 'xxx']);
        $config->validateRequired(['api_key', 'secret']);
    }
}

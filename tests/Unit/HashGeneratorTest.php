<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\Support\HashGenerator;
use PHPUnit\Framework\TestCase;

/**
 * HashGenerator birim testleri.
 */
class HashGeneratorTest extends TestCase
{
    public function test_hmac_sha256(): void
    {
        $result = HashGenerator::hmacSha256('test_data', 'secret_key');
        $expected = hash_hmac('sha256', 'test_data', 'secret_key');
        $this->assertSame($expected, $result);
    }

    public function test_hmac_sha512(): void
    {
        $result = HashGenerator::hmacSha512('test_data', 'secret_key');
        $expected = hash_hmac('sha512', 'test_data', 'secret_key');
        $this->assertSame($expected, $result);
    }

    public function test_hmac_sha256_base64(): void
    {
        $result = HashGenerator::hmacSha256Base64('test_data', 'secret_key');
        $expected = base64_encode(hash_hmac('sha256', 'test_data', 'secret_key', true));
        $this->assertSame($expected, $result);
    }

    public function test_sha256(): void
    {
        $result = HashGenerator::sha256('hello');
        $expected = hash('sha256', 'hello');
        $this->assertSame($expected, $result);
    }

    public function test_sha1(): void
    {
        $result = HashGenerator::sha1('hello');
        $expected = sha1('hello');
        $this->assertSame($expected, $result);
    }

    public function test_base64_encode_decode(): void
    {
        $original = 'Merhaba Dünya!';
        $encoded = HashGenerator::base64Encode($original);
        $decoded = HashGenerator::base64Decode($encoded);
        $this->assertSame($original, $decoded);
    }
}

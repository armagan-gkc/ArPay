<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\Http\HttpResponse;
use PHPUnit\Framework\TestCase;

/**
 * HttpResponse birim testleri.
 */
class HttpResponseTest extends TestCase
{
    public function test_status_code(): void
    {
        $response = new HttpResponse(200, '{"ok": true}');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_body(): void
    {
        $response = new HttpResponse(200, '{"ok": true}');
        $this->assertSame('{"ok": true}', $response->getBody());
    }

    public function test_to_array(): void
    {
        $response = new HttpResponse(200, '{"key": "value", "number": 42}');
        $arr = $response->toArray();

        $this->assertSame('value', $arr['key']);
        $this->assertSame(42, $arr['number']);
    }

    public function test_to_array_with_invalid_json(): void
    {
        $response = new HttpResponse(200, 'not json');
        $this->assertSame([], $response->toArray());
    }

    public function test_is_successful_2xx(): void
    {
        $this->assertTrue((new HttpResponse(200, ''))->isSuccessful());
        $this->assertTrue((new HttpResponse(201, ''))->isSuccessful());
        $this->assertTrue((new HttpResponse(299, ''))->isSuccessful());
    }

    public function test_is_not_successful_4xx_5xx(): void
    {
        $this->assertFalse((new HttpResponse(400, ''))->isSuccessful());
        $this->assertFalse((new HttpResponse(500, ''))->isSuccessful());
    }

    public function test_headers(): void
    {
        $response = new HttpResponse(200, '', ['Content-Type' => 'application/json']);
        $this->assertSame(['Content-Type' => 'application/json'], $response->getHeaders());
    }
}

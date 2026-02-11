<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\Native\Service\CurlHttpClient;

final class CurlHttpClientTest extends TestCase
{
    public function test_request_returns_unsuccessful_response_for_unreachable_endpoint(): void
    {
        if (! function_exists('curl_init')) {
            $this->markTestSkipped('ext-curl is not available');
        }

        $client = new CurlHttpClient;
        $response = $client->request(
            method: 'GET',
            url: 'http://127.0.0.1:1/health',
            timeoutSeconds: 1,
        );

        self::assertFalse($response->isSuccessful());
    }

    public function test_request_supports_post_body_flow(): void
    {
        if (! function_exists('curl_init')) {
            $this->markTestSkipped('ext-curl is not available');
        }

        $client = new CurlHttpClient;
        $response = $client->request(
            method: 'POST',
            url: 'http://127.0.0.1:1/token',
            headers: ['Content-Type: application/x-www-form-urlencoded'],
            body: 'grant_type=client_credentials',
            timeoutSeconds: 1,
        );

        self::assertFalse($response->isSuccessful());
    }
}

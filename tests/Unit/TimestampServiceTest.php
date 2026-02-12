<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Application\DTO\TimestampOptionsDto;
use SignerPHP\Application\Service\TimestampService;
use SignerPHP\Domain\Exception\SignProcessException;
use SignerPHP\Infrastructure\Native\Contract\TimestampTokenProviderInterface;

final class TimestampServiceTest extends TestCase
{
    public function test_request_token_hex_uses_full_content_as_byte_range(): void
    {
        $capture = new class
        {
            public string $content = '';

            /** @var array{0:int,1:int,2:int,3:int}|null */
            public ?array $byteRange = null;
        };

        $provider = new class($capture) implements TimestampTokenProviderInterface
        {
            public function __construct(private object $capture) {}

            public function requestTokenHex(string $signableDocument, array $byteRange, TimestampOptionsDto $options): string
            {
                $this->capture->content = $signableDocument;
                $this->capture->byteRange = $byteRange;

                return 'ABCD';
            }
        };

        $service = new TimestampService($provider);
        $hex = $service->requestTokenHex('probe-content', new TimestampOptionsDto('https://tsa.example'));

        self::assertSame('ABCD', $hex);
        self::assertSame('probe-content', $capture->content);
        self::assertSame([0, strlen('probe-content'), 0, 0], $capture->byteRange);
    }

    public function test_test_connection_returns_failure_with_status_code_when_provider_throws_http_status_message(): void
    {
        $provider = new class implements TimestampTokenProviderInterface
        {
            public function requestTokenHex(string $signableDocument, array $byteRange, TimestampOptionsDto $options): string
            {
                throw new SignProcessException('Could not fetch RFC3161 timestamp response from TSA endpoint. Status: 403.');
            }
        };

        $service = new TimestampService($provider);
        $result = $service->testConnection(new TimestampOptionsDto('https://tsa.example'));

        self::assertFalse($result->success);
        self::assertSame(403, $result->statusCode);
        self::assertStringContainsString('Status: 403', (string) $result->message);
    }
}


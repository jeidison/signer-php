<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Application\DTO\TimestampOptionsDto;
use SignerPHP\Application\Service\TimestampService;
use SignerPHP\Domain\Exception\SignerException;
use SignerPHP\Infrastructure\Native\Contract\TimestampTokenProviderInterface;
use SignerPHP\Presentation\TimestampBuilder;

final class TimestampBuilderTest extends TestCase
{
    public function test_builder_requires_options_for_test_connection(): void
    {
        $this->expectException(SignerException::class);
        $this->expectExceptionMessage('Timestamp options are required. Use withOptions().');

        TimestampBuilder::new()->testConnection();
    }

    public function test_builder_requires_content_for_request_token(): void
    {
        $this->expectException(SignerException::class);
        $this->expectExceptionMessage('Timestamp content is required. Use withContent().');

        TimestampBuilder::new()
            ->withOptions(new TimestampOptionsDto('https://tsa.example'))
            ->requestTokenHex();
    }

    public function test_builder_can_test_connection_and_request_token(): void
    {
        $provider = new class implements TimestampTokenProviderInterface
        {
            public function requestTokenHex(string $signableDocument, array $byteRange, TimestampOptionsDto $options): string
            {
                return strtoupper(bin2hex($signableDocument));
            }
        };

        $service = new TimestampService($provider);

        $builder = TimestampBuilder::new($service)
            ->withOptions(new TimestampOptionsDto('https://tsa.example'))
            ->withContent('abc');

        $result = $builder->testConnection('probe');
        $hex = $builder->requestTokenHex();

        self::assertTrue($result->success);
        self::assertNull($result->statusCode);
        self::assertSame('616263', strtolower($hex));
    }
}

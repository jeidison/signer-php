<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Application\DTO\TimestampOptionsDto;
use SignerPHP\Infrastructure\Native\Contract\TimestampTokenProviderInterface;
use SignerPHP\Infrastructure\Native\Service\DocumentTimestampApplier;
use SignerPHP\Tests\Support\PdfFixtureFactory;

final class DocumentTimestampApplierFlowTest extends TestCase
{
    public function test_apply_appends_doc_timestamp_when_token_provider_returns_hex(): void
    {
        $input = PdfFixtureFactory::minimalPdf();

        $provider = new class implements TimestampTokenProviderInterface
        {
            public array $receivedByteRange = [];

            public string $receivedSignableDocument = '';

            public string $receivedHashAlgorithm = '';

            public function requestTokenHex(string $signableDocument, array $byteRange, TimestampOptionsDto $options): string
            {
                $this->receivedByteRange = $byteRange;
                $this->receivedSignableDocument = $signableDocument;
                $this->receivedHashAlgorithm = $options->hashAlgorithm->value;

                return 'A1B2C3';
            }
        };

        $result = (new DocumentTimestampApplier(timestampTokenProvider: $provider))->apply(
            $input,
            new TimestampOptionsDto(tsaUrl: 'https://tsa.local')
        );

        self::assertNotSame($input, $result);
        self::assertStringContainsString('/DocTimeStamp', $result);
        self::assertCount(4, $provider->receivedByteRange);
        self::assertNotSame('', $provider->receivedSignableDocument);
        self::assertSame('sha256', $provider->receivedHashAlgorithm);
    }

    public function test_apply_throws_when_provider_returns_non_hex_token(): void
    {
        $input = PdfFixtureFactory::minimalPdf();

        $provider = new class implements TimestampTokenProviderInterface
        {
            public function requestTokenHex(string $signableDocument, array $byteRange, TimestampOptionsDto $options): string
            {
                return 'NOT-HEX';
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('RFC3161 timestamp token is not valid hex.');

        (new DocumentTimestampApplier(timestampTokenProvider: $provider))->apply(
            $input,
            new TimestampOptionsDto(tsaUrl: 'https://tsa.local')
        );
    }
}

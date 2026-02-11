<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Domain\Exception\SignProcessException;
use PdfSigner\Infrastructure\Native\Service\DocumentTimestampApplier;
use PdfSigner\Infrastructure\PdfCore\Signature;
use PHPUnit\Framework\TestCase;

final class DocumentTimestampApplierInternalsTest extends TestCase
{
    public function test_normalize_timestamp_hex_size_validates_and_pads_hex(): void
    {
        $applier = new DocumentTimestampApplier;
        $method = new \ReflectionMethod($applier, 'normalizeTimestampHexSize');
        $method->setAccessible(true);

        $normalized = $method->invoke($applier, 'ab12');

        self::assertSame(Signature::SIGNATURE_MAX_LENGTH, strlen($normalized));
        self::assertStringStartsWith('AB12', $normalized);
    }

    public function test_normalize_timestamp_hex_size_throws_for_invalid_hex(): void
    {
        $applier = new DocumentTimestampApplier;
        $method = new \ReflectionMethod($applier, 'normalizeTimestampHexSize');
        $method->setAccessible(true);

        $this->expectException(SignProcessException::class);
        $this->expectExceptionMessage('RFC3161 timestamp token is not valid hex.');
        $method->invoke($applier, 'zz');
    }

    public function test_normalize_timestamp_hex_size_throws_for_empty_token(): void
    {
        $applier = new DocumentTimestampApplier;
        $method = new \ReflectionMethod($applier, 'normalizeTimestampHexSize');
        $method->setAccessible(true);

        $this->expectException(SignProcessException::class);
        $this->expectExceptionMessage('Empty RFC3161 timestamp token.');
        $method->invoke($applier, '   ');
    }

    public function test_normalize_timestamp_hex_size_throws_for_too_large_token(): void
    {
        $applier = new DocumentTimestampApplier;
        $method = new \ReflectionMethod($applier, 'normalizeTimestampHexSize');
        $method->setAccessible(true);

        $this->expectException(SignProcessException::class);
        $this->expectExceptionMessage('RFC3161 token exceeds reserved signature size');
        $method->invoke($applier, str_repeat('A', Signature::SIGNATURE_MAX_LENGTH + 1));
    }

    public function test_extract_byte_range_values_parses_expected_format(): void
    {
        $applier = new DocumentTimestampApplier;
        $method = new \ReflectionMethod($applier, 'extractByteRangeValues');
        $method->setAccessible(true);

        $values = $method->invoke($applier, '[0 10 20 30]');

        self::assertSame([0, 10, 20, 30], $values);
    }

    public function test_extract_byte_range_values_throws_for_invalid_format(): void
    {
        $applier = new DocumentTimestampApplier;
        $method = new \ReflectionMethod($applier, 'extractByteRangeValues');
        $method->setAccessible(true);

        $this->expectException(SignProcessException::class);
        $this->expectExceptionMessage('Could not parse ByteRange for document timestamp.');
        $method->invoke($applier, 'invalid');
    }
}

<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\SignatureObject;
use PHPUnit\Framework\TestCase;

final class SignatureObjectTest extends TestCase
{
    public function test_optional_metadata_fields_are_not_set_when_empty(): void
    {
        $signature = new SignatureObject(10);
        $signature
            ->withLocation('')
            ->withContactInfo('')
            ->withName('')
            ->withReason('');

        self::assertNull($signature['Location']);
        self::assertNull($signature['ContactInfo']);
        self::assertNull($signature['Name']);
        self::assertNull($signature['Reason']);
    }

    public function test_to_pdf_entry_builds_byte_range_and_marker_offset(): void
    {
        $signature = new SignatureObject(11);
        $signature->withSizes(100, 200);
        $entry = $signature->toPdfEntry();

        self::assertStringContainsString('/ByteRange', $entry);
        self::assertGreaterThan(0, $signature->getSignatureMarkerOffset());
    }

    public function test_optional_metadata_fields_are_set_when_present(): void
    {
        $signature = new SignatureObject(12);
        $signature
            ->withLocation('BR')
            ->withContactInfo('contact@example.com');

        self::assertSame('(BR)', (string) $signature['Location']);
        self::assertSame('(contact@example.com)', (string) $signature['ContactInfo']);
    }
}

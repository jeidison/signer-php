<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\XrefEntry;

final class XrefEntryTest extends TestCase
{
    public function test_from_legacy_offset_creates_direct_entry(): void
    {
        $entry = XrefEntry::fromLegacyValue(55);

        self::assertTrue($entry->isDirectOffset());
        self::assertSame(55, $entry->offset());
        self::assertSame(55, $entry->toLegacyValue());
    }

    public function test_from_legacy_object_stream_creates_reference(): void
    {
        $entry = XrefEntry::fromLegacyValue(['stmoid' => 7, 'pos' => 2]);

        self::assertTrue($entry->isObjectStreamReference());
        self::assertSame(7, $entry->objectStreamId());
        self::assertSame(2, $entry->objectStreamPosition());
        self::assertSame(['stmoid' => 7, 'pos' => 2], $entry->toLegacyValue());
    }

    public function test_from_legacy_null_creates_free_entry(): void
    {
        $entry = XrefEntry::fromLegacyValue(null);

        self::assertTrue($entry->isFree());
        self::assertNull($entry->toLegacyValue());
    }
}

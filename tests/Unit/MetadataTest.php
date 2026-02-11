<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\Metadata;

final class MetadataTest extends TestCase
{
    public function test_metadata_keeps_fields_null_when_empty_values_are_passed(): void
    {
        $metadata = Metadata::new()
            ->withName('')
            ->withReason('')
            ->withLocation('')
            ->withContactInfo('');

        self::assertNull($metadata->getName());
        self::assertNull($metadata->getReason());
        self::assertNull($metadata->getLocation());
        self::assertNull($metadata->getContactInfo());
    }

    public function test_metadata_assigns_all_fields_when_non_empty_values_are_passed(): void
    {
        $metadata = Metadata::new()
            ->withName('Alice')
            ->withReason('Approval')
            ->withLocation('Sao Paulo')
            ->withContactInfo('alice@example.com');

        self::assertSame('Alice', $metadata->getName());
        self::assertSame('Approval', $metadata->getReason());
        self::assertSame('Sao Paulo', $metadata->getLocation());
        self::assertSame('alice@example.com', $metadata->getContactInfo());
    }
}

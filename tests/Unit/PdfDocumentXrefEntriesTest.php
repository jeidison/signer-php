<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\XrefEntry;
use PHPUnit\Framework\TestCase;

final class PdfDocumentXrefEntriesTest extends TestCase
{
    public function test_set_xref_table_builds_typed_entries_map(): void
    {
        $document = new PdfDocument;
        $document->setXrefTable([
            1 => 10,
            2 => ['stmoid' => 4, 'pos' => 2],
            3 => null,
        ]);

        $entries = $document->getXrefEntries();

        self::assertInstanceOf(XrefEntry::class, $entries[1]);
        self::assertTrue($entries[1]->isDirectOffset());
        self::assertTrue($entries[2]->isObjectStreamReference());
        self::assertTrue($entries[3]->isFree());
    }
}

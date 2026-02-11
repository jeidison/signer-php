<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\Struct;
use PHPUnit\Framework\TestCase;

final class StructTest extends TestCase
{
    public function test_parse_returns_empty_xref_structure_when_startxref_points_to_zero(): void
    {
        $pdf = "%PDF-1.4\nstartxref\n0\n%%EOF\n";
        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $structure = Struct::new()
            ->withPdfDocument($document)
            ->parse();

        self::assertNull($structure->trailer);
        self::assertSame('PDF-1.4', $structure->version);
        self::assertSame([], $structure->xrefTable);
        self::assertSame(0, $structure->xrefPosition);
    }

    public function test_parse_throws_when_startxref_is_missing(): void
    {
        $document = new PdfDocument;
        $document->setBufferFromString("%PDF-1.4\nno markers\n");

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('startxref not found');

        Struct::new()
            ->withPdfDocument($document)
            ->parse();
    }

    public function test_structure_returns_legacy_array_shape(): void
    {
        $pdf = "%PDF-1.4\nstartxref\n0\n%%EOF\n";
        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $structure = Struct::new()
            ->withPdfDocument($document)
            ->structure();

        self::assertArrayHasKey('trailer', $structure);
        self::assertArrayHasKey('version', $structure);
        self::assertArrayHasKey('xref', $structure);
        self::assertArrayHasKey('xrefposition', $structure);
        self::assertArrayHasKey('xrefversion', $structure);
        self::assertArrayHasKey('revisions', $structure);
    }

    public function test_parse_throws_when_pdf_version_cannot_be_read(): void
    {
        $document = new PdfDocument;
        $document->setBufferFromString('');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to get PDF version');

        Struct::new()
            ->withPdfDocument($document)
            ->parse();
    }

    public function test_parse_throws_when_final_startxref_block_is_malformed(): void
    {
        $document = new PdfDocument;
        $document->setBufferFromString("%PDF-1.4\nstartxref\n10\nEOF\n");

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('startxref and %%EOF not found');

        Struct::new()
            ->withPdfDocument($document)
            ->parse();
    }
}

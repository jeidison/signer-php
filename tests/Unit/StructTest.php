<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\Struct;

final class StructTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function pdfHeaderPrefixVariants(): array
    {
        return [
            'no prefix (conforming)' => ["%PDF-1.4\nstartxref\n0\n%%EOF\n"],
            'UTF-8 BOM prefix (non-conforming)' => ["\xEF\xBB\xBF%PDF-1.4\nstartxref\n0\n%%EOF\n"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pdfHeaderPrefixVariants')]
    public function test_parse_detects_pdf_version_with_various_header_prefixes(string $pdf): void
    {
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

    public function test_parse_throws_when_neither_startxref_nor_xref_table_found(): void
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

    public function test_parse_throws_when_startxref_has_no_numeric_offset_and_no_xref_table(): void
    {
        // 'startxref' present but carries no number and there is no fallback xref table.
        $document = new PdfDocument;
        $document->setBufferFromString("%PDF-1.4\nstartxref\n%%EOF\n");

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('startxref not found');

        Struct::new()
            ->withPdfDocument($document)
            ->parse();
    }

    public function test_parse_resolves_xref_when_startxref_has_offset_but_no_eof_marker(): void
    {
        // Lenient form: startxref carries a valid offset but %%EOF is missing (truncated file).
        // The lenient regex should extract the offset and proceed.
        $xrefBody = "xref\n0 1\n0000000010 00000 n \ntrailer\n<< /Size 1 >>\n";
        $pdf = "%PDF-1.4\n".$xrefBody."startxref\n9\n";
        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $structure = Struct::new()
            ->withPdfDocument($document)
            ->parse();

        self::assertSame('PDF-1.4', $structure->version);
        self::assertSame(9, $structure->xrefPosition);
    }

    public function test_parse_recovers_xref_position_by_backward_scan_when_startxref_has_no_offset(): void
    {
        // 'startxref' present but carries no numeric offset (non-conforming generator).
        // Both strict and lenient regexes fail; backward scan falls back to the xref table.
        // Trailer.php can find "trailer...startxref" because the keyword exists in the buffer.
        $xrefBody = "xref\n0 1\n0000000010 00000 n \ntrailer\n<< /Size 1 >>\nstartxref\n%%EOF\n";
        $pdf = "%PDF-1.4\n".$xrefBody;
        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $structure = Struct::new()
            ->withPdfDocument($document)
            ->parse();

        self::assertSame('PDF-1.4', $structure->version);
        // xrefPosition is the offset of the 'xref' keyword, right after the %PDF-1.4\n header (9 bytes).
        self::assertSame(9, $structure->xrefPosition);
    }
}

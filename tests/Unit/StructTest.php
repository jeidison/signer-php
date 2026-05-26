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
            'long binary prefix beyond first 1024 bytes' => [str_repeat("\x00", 1500)."%PDF-1.4\nstartxref\n0\n%%EOF\n"],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function malformedPdfHeaderVariants(): array
    {
        return [
            'pdf-a variant' => ["%PDF-a.4\nstartxref\n0\n%%EOF\n", 'PDF-1.4'],
            'missing minor digits' => ["%PDF-1.\nstartxref\n0\n%%EOF\n", 'PDF-1.0'],
            'integer-only version token' => ["%PDF-2\nstartxref\n0\n%%EOF\n", 'PDF-2.0'],
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

    #[\PHPUnit\Framework\Attributes\DataProvider('malformedPdfHeaderVariants')]
    public function test_parse_accepts_normalizable_malformed_headers(string $pdf, string $expectedVersion): void
    {
        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $structure = Struct::new()
            ->withPdfDocument($document)
            ->parse();

        self::assertNull($structure->trailer);
        self::assertSame($expectedVersion, $structure->version);
        self::assertSame([], $structure->xrefTable);
        self::assertSame(0, $structure->xrefPosition);
    }

    public function test_parse_falls_back_to_default_version_when_header_is_missing_but_structure_is_pdf_like(): void
    {
        $pdf = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            ."trailer\n<< /Root 1 0 R /Size 3 >>\n%%EOF\n";

        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $structure = Struct::new()
            ->withPdfDocument($document)
            ->parse();

        self::assertSame('PDF-1.4', $structure->version);
        self::assertSame(0, $structure->xrefPosition);
        self::assertArrayHasKey(1, $structure->xrefTable);
        self::assertArrayHasKey(2, $structure->xrefTable);
    }

    public function test_parse_throws_when_header_token_is_not_normalizable(): void
    {
        $document = new PdfDocument;
        $document->setBufferFromString("%PDF-foo\nstartxref\n0\n%%EOF\n");

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PDF version not found');

        Struct::new()
            ->withPdfDocument($document)
            ->parse();
    }

    public function test_parse_throws_when_header_is_missing_and_buffer_is_not_pdf_like(): void
    {
        $document = new PdfDocument;
        $document->setBufferFromString('not a pdf');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PDF version not found');

        Struct::new()
            ->withPdfDocument($document)
            ->parse();
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

    public function test_parse_recovers_xref_when_keyword_is_after_space_on_same_line(): void
    {
        // Regression fixture: malformed PDFs may place `xref` after a space on the
        // same line as a previous token (e.g. `endobj xref`).
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj xref\n0 0\ntrailer\n<< /Size 1 >>\nstartxref\n%%EOF\n";
        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $structure = Struct::new()
            ->withPdfDocument($document)
            ->parse();

        $xrefOffset = strpos($pdf, 'xref');
        if ($xrefOffset === false) {
            self::fail('Could not build same-line xref fixture.');
        }

        self::assertSame('PDF-1.4', $structure->version);
        self::assertSame($xrefOffset, $structure->xrefPosition);
    }

    public function test_parse_recovers_xref_at_buffer_start_with_crlf_keyword_when_startxref_is_missing_offset(): void
    {
        // Synthetic regression fixture from malformed PDFs: xref starts at byte 0 and uses
        // CRLF after the keyword, while startxref exists but has no numeric offset.
        // This forces fallback to the str_starts_with('xref\r\n') branch.
        $pdf = "xref\r\n0 0\ntrailer\n<< /Size 1 >>\nstartxref\n%%EOF\n%PDF-1.4\n";
        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $structure = Struct::new()
            ->withPdfDocument($document)
            ->parse();

        self::assertSame('PDF-1.4', $structure->version);
        self::assertSame(0, $structure->xrefPosition);
        self::assertSame([], $structure->xrefTable);
    }

    public function test_parse_recovers_xref_stream_position_when_startxref_is_missing(): void
    {
        // Regression fixture: malformed file has no startxref section, but does contain
        // a valid xref stream object that should be discovered by fallback scanning.
        $xrefStreamData = chr(1).pack('n', 0).chr(0)
            .chr(1).pack('n', 9).chr(0);
        $xrefLength = strlen($xrefStreamData);

        $xrefObject = "5 0 obj\n<< /Type /XRef /W [1 2 1] /Size 2 /Index [0 2] /Length {$xrefLength} >>\nstream\n"
            .$xrefStreamData
            ."\nendstream\nendobj\n";
        $pdf = "%PDF-1.5\n1 0 obj\n<< /Type /Catalog >>\nendobj\n".$xrefObject."%%EOF\n";
        $xrefOffset = strpos($pdf, '5 0 obj');
        if ($xrefOffset === false) {
            self::fail('Could not build xref stream fixture.');
        }

        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $structure = Struct::new()
            ->withPdfDocument($document)
            ->parse();

        self::assertSame('PDF-1.5', $structure->version);
        self::assertSame($xrefOffset, $structure->xrefPosition);
        self::assertSame(9, $structure->xrefTable[1]);
    }

    public function test_parse_recovers_via_synthetic_structure_when_resolved_xref_is_invalid(): void
    {
        $catalog = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pages = "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n";
        $badXrefObject = "5 0 obj\n<< /Type /XRef /W [1 2 1] /Size 2 /Length 4 >>\nstream\nABCD\nendstream\nendobj\n";

        $prefix = "%PDF-1.5\n".$catalog.$pages;
        $xrefOffset = strlen($prefix);
        $pdf = $prefix.$badXrefObject
            ."trailer\n<< /Root 1 0 R /Size 3 >>\n"
            ."startxref\n{$xrefOffset}\n%%EOF\n";

        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $structure = Struct::new()
            ->withPdfDocument($document)
            ->parse();

        self::assertSame('PDF-1.5', $structure->version);
        self::assertSame(0, $structure->xrefPosition);
        self::assertArrayHasKey(1, $structure->xrefTable);
        self::assertArrayHasKey(2, $structure->xrefTable);
    }

    public function test_parse_rethrows_xref_parsing_exception_when_fallback_structure_cannot_be_recovered(): void
    {
        $xrefObject = "5 0 obj\n<< /Type /XRef /W [1 2 1] /Size 2 /Length 4 >>\nstream\nABCD\nendstream\nendobj\n";
        $pdf = "%PDF-1.5\n"
            .$xrefObject
            ."startxref\n9\n%%EOF\n";

        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid stream for xref table');

        Struct::new()
            ->withPdfDocument($document)
            ->parse();
    }

    public function test_parse_recovers_trailer_and_object_offsets_when_startxref_and_xref_are_missing(): void
    {
        // Regression fixture from corpus-like malformed files: no xref/startxref,
        // but objects and trailer dictionary exist and are still parseable.
        $pdf = "%PDF-1.4\n"
            ."1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            ."trailer\n<< /Root 1 0 R /Size 3 >>\n%%EOF\n";

        $offsetObj1 = strpos($pdf, '1 0 obj');
        $offsetObj2 = strpos($pdf, '2 0 obj');
        if ($offsetObj1 === false || $offsetObj2 === false) {
            self::fail('Could not build no-xref trailer fallback fixture.');
        }

        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $structure = Struct::new()
            ->withPdfDocument($document)
            ->parse();

        self::assertSame('PDF-1.4', $structure->version);
        self::assertSame(0, $structure->xrefPosition);
        self::assertNotNull($structure->trailer);
        self::assertSame($offsetObj1, $structure->xrefTable[1]);
        self::assertSame($offsetObj2, $structure->xrefTable[2]);
    }

    public function test_parse_recovers_with_synthetic_trailer_when_root_reference_exists_without_trailer(): void
    {
        // Regression fixture based on malformed corpus files: no trailer/xref/startxref,
        // but a /Root reference exists in an indirect object.
        $pdf = "%PDF-1.7\n"
            ."1 0 obj <</Type /Catalog /Pages 2 0 R>>\nendobj\n"
            ."2 0 obj <</Type /Pages /Kids [3 0 R] /Count 1>>\nendobj\n"
            ."3 0 obj <</Type /Page /Parent 2 0 R /MediaBox [0 0 10 10]>>\nendobj\n"
            ."2147483647 0 obj <</Root 1 0 R>>\nendobj\n";

        $offsetObj1 = strpos($pdf, '1 0 obj');
        $offsetObj2 = strpos($pdf, '2 0 obj');
        $offsetObj3 = strpos($pdf, '3 0 obj');
        if ($offsetObj1 === false || $offsetObj2 === false || $offsetObj3 === false) {
            self::fail('Could not build synthetic trailer fallback fixture.');
        }

        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $structure = Struct::new()
            ->withPdfDocument($document)
            ->parse();

        self::assertSame('PDF-1.7', $structure->version);
        self::assertSame(0, $structure->xrefPosition);
        self::assertNotNull($structure->trailer);
        self::assertSame($offsetObj1, $structure->xrefTable[1]);
        self::assertSame($offsetObj2, $structure->xrefTable[2]);
        self::assertSame($offsetObj3, $structure->xrefTable[3]);
    }

    public function test_parse_throws_when_startxref_is_missing_and_trailer_is_invalid(): void
    {
        $pdf = "%PDF-1.4\n"
            ."trailer\n<< /Root [ >>\n"
            ."%%EOF\n";

        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('startxref not found');

        Struct::new()
            ->withPdfDocument($document)
            ->parse();
    }

    public function test_parse_throws_when_startxref_is_missing_and_trailer_has_no_objects(): void
    {
        $pdf = "%PDF-1.4\n"
            ."trailer\n<< /Root 1 0 R /Size 1 >>\n%%EOF\n";

        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('startxref not found');

        Struct::new()
            ->withPdfDocument($document)
            ->parse();
    }

    public function test_parse_throws_when_synthetic_root_reference_is_zero_object(): void
    {
        $pdf = "%PDF-1.7\n"
            ."0 0 obj <</Root 0 0 R>>\nendobj\n";

        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('startxref not found');

        Struct::new()
            ->withPdfDocument($document)
            ->parse();
    }

    public function test_parse_throws_when_synthetic_root_reference_exceeds_int_range(): void
    {
        $overflowRoot = str_repeat('9', strlen((string) PHP_INT_MAX) + 1);
        $pdf = "%PDF-1.7\n"
            ."1 0 obj <</Root {$overflowRoot} 0 R>>\nendobj\n";

        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('startxref not found');

        Struct::new()
            ->withPdfDocument($document)
            ->parse();
    }

    public function test_parse_skips_object_zero_in_synthetic_xref_recovery(): void
    {
        $pdf = "%PDF-1.4\n"
            ."0 0 obj\n<< /Type /Ignored >>\nendobj\n"
            ."1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            ."trailer\n<< /Root 1 0 R /Size 3 >>\n%%EOF\n";

        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $structure = Struct::new()
            ->withPdfDocument($document)
            ->parse();

        self::assertArrayNotHasKey(0, $structure->xrefTable);
        self::assertArrayHasKey(1, $structure->xrefTable);
        self::assertArrayHasKey(2, $structure->xrefTable);
    }

    public function test_parse_throws_when_synthetic_root_has_same_length_but_is_greater_than_int_max(): void
    {
        $max = (string) PHP_INT_MAX;
        $lastDigit = (int) substr($max, -1);
        if ($lastDigit === 9) {
            self::markTestSkipped('Cannot create same-length overflow value ending with digit 9.');
        }

        $sameLengthOverflow = substr($max, 0, -1).(string) ($lastDigit + 1);
        $pdf = "%PDF-1.7\n"
            ."1 0 obj <</Root {$sameLengthOverflow} 0 R>>\nendobj\n";

        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('startxref not found');

        Struct::new()
            ->withPdfDocument($document)
            ->parse();
    }

    public function test_parse_throws_when_synthetic_root_reference_contains_non_digit_object_number(): void
    {
        $pdf = "%PDF-1.7\n"
            ."1 0 obj <</Root abc 0 R>>\nendobj\n";

        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('startxref not found');

        Struct::new()
            ->withPdfDocument($document)
            ->parse();
    }

    public function test_parse_throws_when_synthetic_root_reference_contains_non_digit_generation(): void
    {
        $pdf = "%PDF-1.7\n"
            ."1 0 obj <</Root 1 abc R>>\nendobj\n";

        $document = new PdfDocument;
        $document->setBufferFromString($pdf);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('startxref not found');

        Struct::new()
            ->withPdfDocument($document)
            ->parse();
    }
}

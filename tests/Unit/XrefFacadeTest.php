<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use SignerPHP\Infrastructure\PdfCore\Xref\Xref;
use SignerPHP\Infrastructure\PdfCore\Xref\XrefParseResult;
use PHPUnit\Framework\TestCase;

final class XrefFacadeTest extends TestCase
{
    public function test_generate_content_to_xref_includes_existing_objects_and_offsets(): void
    {
        $document = new PdfDocument;
        $document->setBufferFromString('%PDF-1.4');
        $document->addObject(new PDFObject(1, ['Type' => '/Catalog']));

        [$buffer, $offsets] = Xref::new()
            ->withPdfDocument($document)
            ->generateContentToXref();

        self::assertArrayHasKey(0, $offsets);
        self::assertArrayHasKey(1, $offsets);
        self::assertStringContainsString('1 0 obj', $buffer->raw());
    }

    public function test_parse_routes_to_xref14_when_trailer_marker_is_present_after_position(): void
    {
        $document = new PdfDocument;
        $document->setBufferFromString("xref\n0 1\n0000000000 65535 f \ntrailer");

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Trailer not found.');

        Xref::new()
            ->withPdfDocument($document)
            ->withXrefPosition(0)
            ->parse();
    }

    public function test_parse_routes_to_xref15_when_trailer_marker_is_absent(): void
    {
        $document = new PdfDocument;
        $document->setBufferFromString('1 0 obj << /Type /Catalog >> endobj');

        $this->expectException(\Exception::class);

        Xref::new()
            ->withPdfDocument($document)
            ->withXrefPosition(0)
            ->parse();
    }

    public function test_to_legacy_tuple_delegates_to_parse_result(): void
    {
        $xref = new class extends Xref
        {
            public function parse(): XrefParseResult
            {
                return new XrefParseResult(
                    [1 => 15],
                    new PDFValueObject(['Size' => 2]),
                    '1.4'
                );
            }
        };

        [$table, $trailer, $version] = $xref->toLegacyTuple();

        self::assertSame([1 => 15], $table);
        self::assertInstanceOf(PDFValueObject::class, $trailer);
        self::assertSame('1.4', $version);
    }
}

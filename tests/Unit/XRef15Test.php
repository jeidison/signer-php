<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\PDFObject;
use PdfSigner\Infrastructure\PdfCore\Xref\XRef15;
use PHPUnit\Framework\TestCase;

final class XRef15Test extends TestCase
{
    public function test_get_xref_result_fails_when_w_field_has_unsupported_widths(): void
    {
        $xrefObject = new PDFObject(1, [
            'Type' => '/XRef',
            'W' => [9, 1, 1],
            'Size' => 1,
        ]);
        $xrefObject->setStream("\x01\x00\x00");

        $document = new class($xrefObject) extends PdfDocument
        {
            public function __construct(private readonly PDFObject $xrefObject) {}

            public function objectFromString(int|string|null $expectedObjId, int $offset = 0, int &$offsetEnd = 0): PDFObject
            {
                return $this->xrefObject;
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid field widths for cross reference stream.');

        XRef15::new()
            ->withPdfDocument($document)
            ->withXrefPosition(0)
            ->parse();
    }

    public function test_to_legacy_tuple_delegates_to_parse_result(): void
    {
        $xrefObject = new PDFObject(1, [
            'Type' => '/XRef',
            'W' => [1, 1, 1],
            'Size' => 2,
            'Index' => [0, 2],
        ]);
        $xrefObject->setStream("\x00\x00\x00\x01\x10\x00");

        $document = new class($xrefObject) extends PdfDocument
        {
            public function __construct(private readonly PDFObject $xrefObject) {}

            public function objectFromString(int|string|null $expectedObjId, int $offset = 0, int &$offsetEnd = 0): PDFObject
            {
                return $this->xrefObject;
            }
        };

        $tuple = XRef15::new()
            ->withPdfDocument($document)
            ->withXrefPosition(0)
            ->toLegacyTuple();

        self::assertSame(16, $tuple[0][1]);
        self::assertSame('1.5', $tuple[2]);
    }
}

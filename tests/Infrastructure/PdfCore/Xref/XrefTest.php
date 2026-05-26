<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Infrastructure\PdfCore\Xref;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\Xref\Xref;

final class XrefTest extends TestCase
{
    #[DataProvider('parseSuccessCases')]
    public function test_parse_routes_to_expected_parser_and_returns_expected_table(
        string $buffer,
        int $xrefPosition,
        string $expectedVersion,
        int $expectedObjectId,
        int $expectedOffset
    ): void {
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);

        $result = Xref::new()
            ->withPdfDocument($document)
            ->withXrefPosition($xrefPosition)
            ->parse();

        self::assertSame($expectedVersion, $result->minimumPdfVersion);
        self::assertSame($expectedOffset, $result->table[$expectedObjectId]);
    }

    #[DataProvider('parseFailureCases')]
    public function test_parse_throws_expected_exception_for_ambiguous_or_invalid_cases(
        string $buffer,
        int $xrefPosition,
        string $expectedMessage
    ): void {
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($expectedMessage);

        Xref::new()
            ->withPdfDocument($document)
            ->withXrefPosition($xrefPosition)
            ->parse();
    }

    /** @return array<string, array{string, int, string, int, int}> */
    public static function parseSuccessCases(): array
    {
        $streamEntry = chr(1).chr(10).chr(0);

        return [
            'xref stream at xref position' => [
                "1 0 obj\n"
                ."<< /Type /XRef /Length 3 /W [1 1 1] /Size 2 /Index [1 1] >>\n"
                ."stream\n"
                .$streamEntry."\n"
                ."endstream\n"
                ."endobj\n",
                0,
                '1.5',
                1,
                10,
            ],
            'xref stream when xref position points before object header' => [
                "\n1 0 obj\n"
                ."<< /Type /XRef /Length 3 /W [1 1 1] /Size 2 /Index [1 1] >>\n"
                ."stream\n"
                .$streamEntry."\n"
                ."endstream\n"
                ."endobj\n",
                0,
                '1.5',
                1,
                10,
            ],
            'classic xref keyword at xref position' => [
                "xref\n0 2\n0000000000 65535 f \n0000000010 00000 n \ntrailer\n<< /Size 2 >>\nstartxref\n0\n%%EOF\n",
                0,
                '1.4',
                1,
                10,
            ],
            'classic xref with prefixed whitespace before keyword' => [
                "  \n xref\n0 2\n0000000000 65535 f \n0000000010 00000 n \ntrailer\n<< /Size 2 >>\nstartxref\n0\n%%EOF\n",
                0,
                '1.4',
                1,
                10,
            ],
        ];
    }

    /** @return array<string, array{string, int, string}> */
    public static function parseFailureCases(): array
    {
        return [
            'xref position beyond end of file, backward scan recovers but trailer incomplete' => [
                // startxref offset is beyond EOF; backward scan finds xref at position 0 and
                // starts parsing, but the trailer dict has no 'startxref' token following it,
                // causing Trailer::getTrailer() to fail.
                "xref\n0 1\n0000000000 65535 f \ntrailer",
                10_000,
                'Trailer not found.',
            ],
            'stream fallback for ambiguous content without trailer' => [
                "garbage-at-start\n1 0 obj\n<< /Type /Catalog >>\nendobj\n",
                0,
                'Invalid xref table',
            ],
            'classic fallback for ambiguous content with trailer' => [
                "garbage-at-start\ntrailer\n<< /Size 1 >>\n",
                0,
                'Xref tag not found at position 0',
            ],
        ];
    }
}

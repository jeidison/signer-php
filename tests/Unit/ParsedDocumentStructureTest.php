<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\ParsedDocumentStructure;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;

final class ParsedDocumentStructureTest extends TestCase
{
    public function test_to_array_keeps_legacy_shape(): void
    {
        $structure = new ParsedDocumentStructure(
            trailer: new PDFValueSimple('trailer'),
            version: 'PDF-1.7',
            xrefTable: [1 => 10, 2 => ['stmoid' => 8, 'pos' => 1], 3 => null],
            xrefPosition: 123,
            xrefVersion: '1.5',
            revisions: [20, 40],
        );

        $array = $structure->toArray();

        self::assertArrayHasKey('trailer', $array);
        self::assertSame('PDF-1.7', $array['version']);
        self::assertSame(123, $array['xrefposition']);
        self::assertSame('1.5', $array['xrefversion']);
        self::assertSame([20, 40], $array['revisions']);
        self::assertSame([1 => 10, 2 => ['stmoid' => 8, 'pos' => 1], 3 => null], $array['xref']);
    }
}

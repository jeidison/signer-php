<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use SignerPHP\Infrastructure\PdfCore\Xref\XrefParseResult;

final class XrefParseResultTest extends TestCase
{
    public function test_to_legacy_tuple_keeps_ordering_and_values(): void
    {
        $result = new XrefParseResult(
            table: [1 => 20, 2 => ['stmoid' => 9, 'pos' => 3]],
            trailer: new PDFValueSimple('trailer'),
            minimumPdfVersion: '1.5',
        );

        [$table, $trailer, $version] = $result->toLegacyTuple();

        self::assertSame([1 => 20, 2 => ['stmoid' => 9, 'pos' => 3]], $table);
        self::assertSame('trailer', (string) $trailer);
        self::assertSame('1.5', $version);
    }

    public function test_to_legacy_xref_tuple_keeps_same_shape_and_values(): void
    {
        $result = new XrefParseResult(
            table: [7 => 90],
            trailer: new PDFValueSimple('trailer2'),
            minimumPdfVersion: '1.4',
        );

        [$table, $trailer, $version] = $result->toLegacyXrefTuple();

        self::assertSame([7 => 90], $table);
        self::assertSame('trailer2', (string) $trailer);
        self::assertSame('1.4', $version);
    }
}

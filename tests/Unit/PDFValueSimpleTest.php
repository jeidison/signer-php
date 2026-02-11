<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueType;

final class PDFValueSimpleTest extends TestCase
{
    public function test_push_concatenates_when_same_type(): void
    {
        $left = new PDFValueSimple('A');
        $right = new PDFValueSimple('B');

        self::assertTrue($left->push($right));
        self::assertSame('A B', (string) $left);
    }

    public function test_push_returns_false_for_different_type(): void
    {
        $value = new PDFValueSimple('A');

        self::assertFalse($value->push(new PDFValueType('Name')));
        self::assertSame('A', (string) $value);
    }

    public function test_as_object_reference_or_null_parses_reference_and_rejects_invalid_values(): void
    {
        $reference = new PDFValueSimple("  12 0 R  \n");
        $invalid = new PDFValueSimple('12 R');

        self::assertSame(12, $reference->asObjectReferenceOrNull());
        self::assertNull($invalid->asObjectReferenceOrNull());
    }

    public function test_as_int_or_null_handles_numeric_and_non_numeric_values(): void
    {
        self::assertSame(42, (new PDFValueSimple('42'))->asIntOrNull());
        self::assertSame(12, (new PDFValueSimple('12.8'))->asIntOrNull());
        self::assertNull((new PDFValueSimple('abc'))->asIntOrNull());
    }
}

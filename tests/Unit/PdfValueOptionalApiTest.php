<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueList;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;

final class PdfValueOptionalApiTest extends TestCase
{
    public function test_as_int_or_null_returns_int_when_numeric(): void
    {
        $value = new PDFValueSimple('42');

        self::assertSame(42, $value->asIntOrNull());
        self::assertSame(42, $value->getInt());
    }

    public function test_as_int_or_null_returns_null_when_not_numeric(): void
    {
        $value = new PDFValueSimple('abc');

        self::assertNull($value->asIntOrNull());
        self::assertFalse($value->getInt());
    }

    public function test_as_object_reference_or_null_for_simple_reference(): void
    {
        $value = new PDFValueSimple('12 0 R');

        self::assertSame(12, $value->asObjectReferenceOrNull());
        self::assertSame(12, $value->getObjectReferenced());
    }

    public function test_as_object_reference_or_null_for_list_references(): void
    {
        $list = new PDFValueList([new PDFValueSimple('10 0 R'), new PDFValueSimple('11 0 R')]);

        self::assertSame([10, 11], $list->asObjectReferenceOrNull());
        self::assertSame([10, 11], $list->getObjectReferenced());
    }
}

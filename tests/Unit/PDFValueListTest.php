<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueList;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;

final class PDFValueListTest extends TestCase
{
    public function test_as_object_reference_or_null_returns_empty_array_for_empty_list(): void
    {
        $list = new PDFValueList([]);

        self::assertSame([], $list->asObjectReferenceOrNull());
    }

    public function test_as_object_reference_or_null_returns_null_for_non_reference_text(): void
    {
        $list = new PDFValueList([new PDFValueSimple('abc')]);

        self::assertNull($list->asObjectReferenceOrNull());
    }

    public function test_push_accepts_nested_list_values(): void
    {
        $list = new PDFValueList([new PDFValueSimple(1)]);
        $nested = new PDFValueList([new PDFValueSimple(2), new PDFValueSimple(3)]);

        self::assertTrue($list->push($nested));
        self::assertSame(['1', '2', '3'], array_map(static fn ($v) => (string) $v, $list->val()));
    }

    public function test_val_list_mode_flattens_simple_values(): void
    {
        $list = new PDFValueList([
            new PDFValueSimple('10 0 R'),
            new PDFValueSimple('20 0 R'),
        ]);

        self::assertSame(['10', '0', 'R', '20', '0', 'R'], $list->val(true));
    }
}

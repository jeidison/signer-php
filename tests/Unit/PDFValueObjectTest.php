<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueList;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueReference;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use PHPUnit\Framework\TestCase;

final class PDFValueObjectTest extends TestCase
{
    public function test_from_array_returns_null_when_numeric_key_exists(): void
    {
        self::assertNull(PDFValueObject::fromArray([0 => 'invalid']));
    }

    public function test_from_string_rejects_invalid_tokens_and_unfinished_field(): void
    {
        self::assertNull(PDFValueObject::fromString(''));
        self::assertNull(PDFValueObject::fromString('Type /Catalog'));
        self::assertNull(PDFValueObject::fromString('/ /Catalog'));
        self::assertNull(PDFValueObject::fromString('/Type'));
    }

    public function test_set_get_has_remove_and_offset_set_support_conversions(): void
    {
        $object = new PDFValueObject(['Type' => '/Catalog']);

        self::assertTrue($object->has('Type'));
        self::assertSame('Catalog', $object->get('Type')?->val());

        $object->set('Count', 2);
        self::assertSame('2', (string) $object->get('Count'));

        $object['Kids'] = [new PDFValueReference(3)];
        self::assertInstanceOf(PDFValueList::class, $object->get('Kids'));

        $object['Count'] = null;
        self::assertNull($object->get('Count'));

        $object->remove('Type');
        self::assertFalse($object->has('Type'));
    }

    public function test_to_string_formats_values_by_leading_character_and_empty_value(): void
    {
        $object = new PDFValueObject([
            'Name' => '/Catalog',
            'Nums' => [1, 2],
            'Text' => 'hello world',
            'Hex' => '<ABCD>',
            'Empty' => '',
            'Ref' => new PDFValueSimple('2 0 R'),
        ]);

        $text = (string) $object;

        self::assertStringContainsString('/Name/Catalog', $text);
        self::assertStringContainsString('/Nums[1 2]', $text);
        self::assertStringContainsString('/Text(hello world)', $text);
        self::assertStringContainsString('/Hex<ABCD>', $text);
        self::assertStringContainsString('/Empty', $text);
        self::assertStringContainsString('/Ref 2 0 R', $text);
    }
}

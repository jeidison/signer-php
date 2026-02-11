<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValue;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueList;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueString;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueType;
use PHPUnit\Framework\TestCase;

final class PDFValueBaseTest extends TestCase
{
    public function test_array_access_on_non_array_values_returns_null_or_false_and_unset_throws(): void
    {
        $value = new class('abc') extends PDFValue {};

        self::assertFalse(isset($value['x']));
        self::assertNull($value['x']);
        $value['x'] = 1;
        self::assertNull($value['x']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid offset');
        unset($value['x']);
    }

    public function test_array_access_on_array_values_supports_set_get_and_unset(): void
    {
        $value = new class(['k' => 'v']) extends PDFValue {};

        self::assertTrue(isset($value['k']));
        self::assertSame('v', $value['k']);
        $value['k'] = 'x';
        self::assertSame('x', $value['k']);
        unset($value['k']);
        self::assertFalse(isset($value['k']));
    }

    public function test_default_optional_methods_return_null_or_false_semantics(): void
    {
        $value = new class('abc') extends PDFValue {};

        self::assertFalse($value->push('x'));
        self::assertNull($value->asIntOrNull());
        self::assertNull($value->asObjectReferenceOrNull());
        self::assertNull($value->getKeys());
        self::assertSame('abc', (string) $value);
        self::assertSame('abc', $value->val());
    }

    public function test_convert_wrapper_covers_supported_primitive_and_collection_types(): void
    {
        $convertedInt = TestPdfValue::convertPublic(10);
        $convertedFloat = TestPdfValue::convertPublic(10.5);
        $convertedType = TestPdfValue::convertPublic('/Catalog');
        $convertedString = TestPdfValue::convertPublic('hello world');
        $convertedSimpleString = TestPdfValue::convertPublic('abc');
        $convertedEmpty = TestPdfValue::convertPublic('');
        $convertedObject = TestPdfValue::convertPublic(['Type' => '/Catalog']);
        $convertedList = TestPdfValue::convertPublic([1, '/Type', 'abc']);
        $convertedEmptyList = TestPdfValue::convertPublic([]);
        self::assertInstanceOf(PDFValueSimple::class, $convertedInt);
        self::assertInstanceOf(PDFValueSimple::class, $convertedFloat);
        self::assertInstanceOf(PDFValueType::class, $convertedType);
        self::assertInstanceOf(PDFValueString::class, $convertedString);
        self::assertInstanceOf(PDFValueSimple::class, $convertedSimpleString);
        self::assertInstanceOf(PDFValueSimple::class, $convertedEmpty);
        self::assertInstanceOf(PDFValueObject::class, $convertedObject);
        self::assertInstanceOf(PDFValueList::class, $convertedList);
        self::assertInstanceOf(PDFValueList::class, $convertedEmptyList);
    }

    public function test_convert_wrapper_throws_for_unsupported_object_type(): void
    {
        $this->expectException(\TypeError::class);

        TestPdfValue::convertPublic(new \stdClass);
    }
}

final class TestPdfValue extends PDFValue
{
    public static function convertPublic(mixed $value): mixed
    {
        return self::convert($value);
    }
}

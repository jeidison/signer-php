<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueType;

final class PdfCoreObjectModelTest extends TestCase
{
    public function test_typed_field_accessors_work(): void
    {
        $object = new PDFObject(10, ['Type' => '/Catalog']);

        self::assertTrue($object->hasField('Type'));
        self::assertSame('/Catalog', (string) $object->getField('Type'));

        $object->setField('Count', 3);
        self::assertSame('3', (string) $object->getField('Count'));

        $object->removeField('Count');
        self::assertNull($object->getField('Count'));
    }

    public function test_array_access_remains_compatible_with_typed_storage(): void
    {
        $object = new PDFObject(11);
        $object['Filter'] = '/FlateDecode';

        self::assertInstanceOf(PDFValueType::class, $object->getField('Filter'));

        $object['Length'] = 128;
        self::assertInstanceOf(PDFValueSimple::class, $object->getField('Length'));

        unset($object['Length']);
        self::assertFalse(isset($object['Length']));
    }

    public function test_set_null_removes_field_in_dictionary(): void
    {
        $object = new PDFObject(12, ['Producer' => 'v1']);

        $object['Producer'] = null;

        self::assertFalse($object->hasField('Producer'));
    }
}

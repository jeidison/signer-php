<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueList;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;

final class PDFObjectTest extends TestCase
{
    public function test_constructor_accepts_pdf_value_object_and_scalar_fields(): void
    {
        $object = new PDFObject(7, new PDFValueObject(['Type' => '/Catalog']));

        self::assertSame(7, $object->getOid());
        self::assertTrue($object->hasField('Type'));
        self::assertStringContainsString('7 0 obj', $object->toPdfEntry());
    }

    public function test_get_stream_throws_for_unknown_filter(): void
    {
        $object = new PDFObject(1, ['Filter' => '/Unknown']);
        $object->setStream('abc');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unknown compression method');

        $object->getStream(false);
    }

    public function test_get_stream_decodes_flate_predictor_one(): void
    {
        $object = new PDFObject(1, [
            'Filter' => '/FlateDecode',
            'DecodeParms' => [
                'Predictor' => 1,
                'Columns' => 3,
                'Colors' => 1,
                'BitsPerComponent' => 8,
            ],
        ]);

        $payload = gzcompress('abc');
        self::assertIsString($payload);
        $object->setStream($payload);

        self::assertSame('abc', $object->getStream(false));
    }

    public function test_get_stream_throws_for_invalid_predictor_parameters(): void
    {
        $object = new PDFObject(1, [
            'Filter' => '/FlateDecode',
            'DecodeParms' => [
                'Predictor' => 15,
                'Columns' => 1,
                'Colors' => 2,
                'BitsPerComponent' => 8,
            ],
        ]);

        $payload = gzcompress(chr(0).'A');
        self::assertIsString($payload);
        $object->setStream($payload);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only one color channel is supported');

        $object->getStream(false);
    }

    public function test_get_stream_throws_for_unsupported_png_filter_byte(): void
    {
        $object = new PDFObject(1, [
            'Filter' => '/FlateDecode',
            'DecodeParms' => [
                'Predictor' => 15,
                'Columns' => 1,
                'Colors' => 1,
                'BitsPerComponent' => 8,
            ],
        ]);

        $payload = gzcompress(chr(3).'A');
        self::assertIsString($payload);
        $object->setStream($payload);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported PNG predictor filter');

        $object->getStream(false);
    }

    public function test_set_stream_with_flate_filter_stores_compressed_data_and_length(): void
    {
        $object = new PDFObject(1, ['Filter' => '/FlateDecode']);
        $object->setStream('abcd', false);

        self::assertNotSame('abcd', $object->getStream(true));
        self::assertSame(strlen((string) $object->getStream(true)), $object['Length']->asIntOrNull());
    }

    public function test_object_api_methods_cover_basic_field_and_offset_operations(): void
    {
        $object = new PDFObject(10, ['Type' => '/Catalog'], generation: 2);

        self::assertSame(2, $object->getGeneration());
        self::assertContains('Type', $object->getKeys());
        self::assertTrue($object->hasField('Type'));
        self::assertNotNull($object->getField('Type'));

        $returned = $object->setField('Author', 'John');
        self::assertSame($object, $returned);
        self::assertSame('John', (string) $object['Author']);

        $object['Subject'] = 'Tests';
        self::assertTrue(isset($object['Subject']));
        unset($object['Subject']);
        self::assertFalse(isset($object['Subject']));

        $object->removeField('Author');
        self::assertFalse($object->hasField('Author'));

        $listObject = new PDFObject(11, ['Items' => new PDFValueList]);
        self::assertFalse($listObject->push(new PDFValueSimple(1)));
    }

    public function test_set_oid_and_serialization_formats(): void
    {
        $object = new PDFObject(1, ['Type' => '/XObject']);
        $object->setOid(12);
        $object->setStream('raw-stream');

        self::assertSame(12, $object->getOid());
        self::assertStringContainsString("12 0 obj\n", (string) $object);
        self::assertStringContainsString("stream\r\nraw-stream", $object->toPdfEntry());
    }

    public function test_constructor_accepts_non_object_pdf_value_input(): void
    {
        $input = new PDFValueList([
            new PDFValueSimple(1),
            new PDFValueSimple(2),
        ]);
        $object = new PDFObject(9, $input);

        self::assertInstanceOf(PDFValueObject::class, $object->getValue());
        self::assertSame([0, 1], $object->getKeys());
    }

    public function test_get_stream_decodes_png_predictor_sub_filter(): void
    {
        $object = new PDFObject(1, [
            'Filter' => '/FlateDecode',
            'DecodeParms' => [
                'Predictor' => 15,
                'Columns' => 3,
                'Colors' => 1,
                'BitsPerComponent' => 8,
            ],
        ]);

        $inflated = chr(1).chr(10).chr(5).chr(1);
        $payload = gzcompress($inflated);
        self::assertIsString($payload);
        $object->setStream($payload);

        $decoded = $object->getStream(false);
        self::assertSame([10, 15, 16], array_values(unpack('C*', $decoded)));
    }

    public function test_get_stream_decodes_png_predictor_up_filter(): void
    {
        $object = new PDFObject(1, [
            'Filter' => '/FlateDecode',
            'DecodeParms' => [
                'Predictor' => 12,
                'Columns' => 2,
                'Colors' => 1,
                'BitsPerComponent' => 8,
            ],
        ]);

        $line1 = chr(0).chr(2).chr(3);
        $line2 = chr(2).chr(1).chr(1);
        $payload = gzcompress($line1.$line2);
        self::assertIsString($payload);
        $object->setStream($payload);

        $decoded = $object->getStream(false);
        self::assertSame([2, 3, 3, 4], array_values(unpack('C*', $decoded)));
    }

    public function test_get_stream_throws_for_invalid_flate_payload(): void
    {
        $object = new PDFObject(1, ['Filter' => '/FlateDecode']);
        $object->setStream('not-compressed');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to inflate FlateDecode stream.');
        set_error_handler(static function (): bool {
            return true;
        });
        try {
            $object->getStream(false);
        } finally {
            restore_error_handler();
        }
    }

    public function test_get_stream_throws_for_invalid_columns_when_predictor_requires_it(): void
    {
        $object = new PDFObject(1, [
            'Filter' => '/FlateDecode',
            'DecodeParms' => [
                'Predictor' => 15,
                'Columns' => 'abc',
                'Colors' => 1,
                'BitsPerComponent' => 8,
            ],
        ]);

        $payload = gzcompress(chr(0).chr(1));
        self::assertIsString($payload);
        $object->setStream($payload);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid column count for stream decoding');

        $object->getStream(false);
    }

    public function test_get_stream_throws_for_invalid_bits_per_component(): void
    {
        $object = new PDFObject(1, [
            'Filter' => '/FlateDecode',
            'DecodeParms' => [
                'Predictor' => 15,
                'Columns' => 1,
                'Colors' => 1,
                'BitsPerComponent' => 4,
            ],
        ]);

        $payload = gzcompress(chr(0).chr(1));
        self::assertIsString($payload);
        $object->setStream($payload);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only 8 bits per component are supported');

        $object->getStream(false);
    }

    public function test_get_stream_throws_for_unsupported_predictor_value(): void
    {
        $object = new PDFObject(1, [
            'Filter' => '/FlateDecode',
            'DecodeParms' => [
                'Predictor' => 9,
                'Columns' => 1,
                'Colors' => 1,
                'BitsPerComponent' => 8,
            ],
        ]);

        $payload = gzcompress(chr(0).chr(1));
        self::assertIsString($payload);
        $object->setStream($payload);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only PNG predictors are supported');

        $object->getStream(false);
    }
}

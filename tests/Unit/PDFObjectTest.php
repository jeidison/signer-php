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

    public function test_get_stream_accepts_filter_name_without_leading_slash(): void
    {
        $object = new PDFObject(1, ['Filter' => 'FlateDecode']);

        $payload = gzcompress('abc');
        self::assertIsString($payload);
        $object->setStream($payload);

        self::assertSame('abc', $object->getStream(false));
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

    /**
     * @return array<string, array{callable(string):string|false}>
     */
    public static function flateEncodeVariants(): array
    {
        return [
            'zlib (RFC 1950 — mandated by ISO 32000)' => ['gzcompress'],
            'raw deflate (RFC 1951 — non-conforming generators)' => ['gzdeflate'],
            'gzip (RFC 1952 — rare non-conforming generators)' => ['gzencode'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('flateEncodeVariants')]
    public function test_get_stream_decodes_flatedecode_for_all_wire_formats(callable $encoder): void
    {
        $object = new PDFObject(1, ['Filter' => '/FlateDecode']);

        $payload = $encoder('abc');
        self::assertIsString($payload);
        $object->setStream($payload);

        self::assertSame('abc', $object->getStream(false));
    }

    public function test_get_stream_decodes_flate_stream_with_trailing_garbage_suffix(): void
    {
        $object = new PDFObject(1, ['Filter' => '/FlateDecode']);

        $payload = gzcompress('abc');
        self::assertIsString($payload);
        $object->setStream($payload."\x00\x00TRAIL");

        self::assertSame('abc', $object->getStream(false));
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

    // ----------------------------------------------------------------
    // Corpus hardening regression tests (problem #1: FlateDecode failures)
    // ----------------------------------------------------------------

    /**
     * Problem #1 (20 corpus cases): Valid zlib payload preceded by many bytes
     * containing fake-looking zlib headers that exhaust the scan attempt limit.
     *
     * tryInflateWithBoundedPrefixSkip() stops at 2048 bytes (payload beyond).
     * tryInflateByHeaderScan() stops after 64 candidate attempts (64 fake
     * headers precede the real one at offset 65*40 = 2600 bytes).
     *
     * Expected: decompressed content returned after increasing the attempt limit.
     */
    public function test_get_stream_decodes_flate_with_real_payload_after_64_fake_headers(): void
    {
        $expected = 'corpus-attempt-limit-payload';
        $validZlib = gzcompress($expected);
        self::assertIsString($validZlib);

        // 65 fake valid zlib headers (0x78 0x9C), each padded to 40 bytes with
        // garbage so decompression fails. Total: 65 × 40 = 2600 bytes.
        // The header scan finds candidates at offsets 40, 80, …, 2560 → exactly
        // 64 attempts, all failing.  The 65th fake block at 2560 exhausts the
        // limit; the real payload at offset 2600 is never reached.
        $fakeBlock = "\x78\x9C" . str_repeat("\xFF", 38); // 40 bytes: valid-looking header + garbage
        $garbage = str_repeat($fakeBlock, 65); // 2600 bytes, provides exactly 64 scan candidates

        $object = new PDFObject(1, ['Filter' => '/FlateDecode']);
        $object->setStream($garbage . $validZlib);

        // Must decompress even after exhausting the old 64-attempt limit
        self::assertSame($expected, $object->getStream(false));
    }

    /**
     * Problem #1 variant: Valid gzip payload buried after 70 000 bytes of binary
     * junk. tryInflateByHeaderScan() only scans 65536 bytes, so the gzip magic
     * bytes are never reached and inflation fails.
     *
     * Reproduce: 70 000-byte garbage prefix + valid gzdecode() payload.
     * Expected: decompressed content returned successfully.
     */
    public function test_get_stream_decodes_flate_with_gzip_header_beyond_65536_byte_scan_window(): void
    {
        $expected = 'corpus-gzip-payload';
        $validGzip = gzencode($expected);
        self::assertIsString($validGzip);

        $garbage = str_repeat("\x00\x01", 35000); // 70 000 bytes, no valid headers
        $object = new PDFObject(1, ['Filter' => '/FlateDecode']);
        $object->setStream($garbage.$validGzip);

        // Must find and decompress the gzip payload beyond the 65536-byte window
        self::assertSame($expected, $object->getStream(false));
    }
}

<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Infrastructure\PdfCore;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueList;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;

final class PDFObjectTest extends TestCase
{
    public function test_get_stream_decodes_flate_filter_declared_as_single_item_array(): void
    {
        $object = new PDFObject(1, [
            'Filter' => new PDFValueList([
                new PDFValueSimple('FlateDecode'),
            ]),
        ]);

        $payload = gzcompress('abc');
        self::assertIsString($payload);
        $object->setStream($payload);

        self::assertSame('abc', $object->getStream(false));
    }

    /** @return array<string, array{callable, string}> */
    public static function flateStreamEncodings(): array
    {
        return [
            'zlib (RFC 1950) — conforming PDF generators' => [
                static fn () => gzcompress('hello world'),
                'hello world',
            ],
            'raw deflate (RFC 1951) — non-conforming generators, no wrapper header' => [
                static fn () => gzdeflate('hello world'),
                'hello world',
            ],
            'gzip (RFC 1952) — uncommon but encountered in the wild' => [
                static fn () => gzencode('hello world'),
                'hello world',
            ],
        ];
    }

    /**
     * @param  callable(): string  $buildStream
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('flateStreamEncodings')]
    public function test_get_stream_decodes_all_flate_encoding_variants(callable $buildStream, string $expected): void
    {
        $object = new PDFObject(1, ['Filter' => new PDFValueSimple('/FlateDecode')]);
        $object->setStream($buildStream());

        self::assertSame($expected, $object->getStream(false));
    }

    public function test_get_stream_throws_when_flate_stream_is_genuinely_corrupt(): void
    {
        $object = new PDFObject(1, ['Filter' => new PDFValueSimple('/FlateDecode')]);
        $object->setStream("\x00\x01\x02\x03corrupt bytes");

        $this->expectException(\SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException::class);
        $this->expectExceptionMessage('Failed to inflate FlateDecode stream.');

        $object->getStream(false);
    }

    public function test_get_stream_decodes_flate_stream_with_leading_whitespace_prefix(): void
    {
        // Regression pattern from corpus: some malformed files include an extra whitespace
        // byte before a valid Flate payload.
        $payload = gzcompress('hello world');
        self::assertIsString($payload);

        $object = new PDFObject(1, ['Filter' => new PDFValueSimple('/FlateDecode')]);
        $object->setStream("\n".$payload);

        self::assertSame('hello world', $object->getStream(false));
    }

    public function test_get_stream_decodes_flate_stream_with_non_whitespace_prefix(): void
    {
        // Regression pattern from corpus: some inputs prepend non-whitespace bytes
        // before a valid compressed Flate payload.
        $payload = gzcompress('hello world');
        self::assertIsString($payload);

        $object = new PDFObject(1, ['Filter' => new PDFValueSimple('/FlateDecode')]);
        $object->setStream('XX'.$payload);

        self::assertSame('hello world', $object->getStream(false));
    }

    public function test_get_stream_decodes_ascii85_then_flate_filter_chain(): void
    {
        $flatePayload = gzcompress('hello world');
        self::assertIsString($flatePayload);

        $object = new PDFObject(1, [
            'Filter' => new PDFValueList([
                new PDFValueSimple('ASCII85Decode'),
                new PDFValueSimple('FlateDecode'),
            ]),
        ]);
        $object->setStream(self::ascii85Encode($flatePayload));

        self::assertSame('hello world', $object->getStream(false));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidAscii85Payloads(): array
    {
        return [
            'z marker after a partial tuple' => ['!z'],
            'out of range ascii85 byte' => ['~'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidAscii85Payloads')]
    public function test_get_stream_throws_for_invalid_ascii85_payload(string $payload): void
    {
        $object = new PDFObject(1, ['Filter' => new PDFValueSimple('/ASCII85Decode')]);
        $object->setStream($payload);

        $this->expectException(\SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException::class);
        $this->expectExceptionMessage('Invalid ASCII85 stream data.');

        $object->getStream(false);
    }

    public function test_get_stream_decodes_flate_filter_when_filter_name_is_unprefixed_scalar_value(): void
    {
        $object = new PDFObject(1, ['Filter' => new PDFValueSimple('FlateDecode')]);

        $payload = gzcompress('hello world');
        self::assertIsString($payload);
        $object->setStream($payload);

        self::assertSame('hello world', $object->getStream(false));
    }

    public function test_get_stream_decodes_flate_filter_when_filter_name_is_returned_as_plain_string(): void
    {
        $filterValue = new class(['Filter' => 'FlateDecode']) extends PDFValueObject
        {
            public function offsetExists($offset): bool
            {
                return $offset === 'Filter';
            }

            public function offsetGet($offset): mixed
            {
                if ($offset === 'Filter') {
                    return 'FlateDecode';
                }

                return null;
            }
        };

        $object = new PDFObject(1, $filterValue);

        $payload = gzcompress('hello world');
        self::assertIsString($payload);
        $object->setStream($payload);

        self::assertSame('hello world', $object->getStream(false));
    }

    private static function ascii85Encode(string $data): string
    {
        $out = '';
        $length = strlen($data);

        for ($i = 0; $i < $length; $i += 4) {
            $chunk = substr($data, $i, 4);
            $chunkLength = strlen($chunk);
            if ($chunkLength < 4) {
                $chunk = str_pad($chunk, 4, "\0", STR_PAD_RIGHT);
            }

            $value = unpack('N', $chunk)[1];
            if ($chunkLength === 4 && $value === 0) {
                $out .= 'z';

                continue;
            }

            $encoded = '';
            for ($j = 0; $j < 5; $j++) {
                $encoded = chr(($value % 85) + 33).$encoded;
                $value = intdiv($value, 85);
            }

            $out .= $chunkLength < 4 ? substr($encoded, 0, $chunkLength + 1) : $encoded;
        }

        return '<~'.$out.'~>';
    }
}

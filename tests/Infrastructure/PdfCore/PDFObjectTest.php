<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Infrastructure\PdfCore;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueList;
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
}

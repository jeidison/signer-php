<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use Exception;
use PdfSigner\Infrastructure\PdfCore\Utils\ImageParser;
use PHPUnit\Framework\TestCase;

final class ImageParserTest extends TestCase
{
    public function test_parse_jpeg_returns_expected_metadata_for_valid_image(): void
    {
        $parser = new ImageParser;
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAf//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDAREAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAVEQEBAAAAAAAAAAAAAAAAAAABAP/aAAwDAQACEAMQAAAB6gD/xAAUEQEAAAAAAAAAAAAAAAAAAAAQ/9oACAEBAAEFAmP/xAAUEQEAAAAAAAAAAAAAAAAAAAAQ/9oACAEDAQE/AT//xAAUEQEAAAAAAAAAAAAAAAAAAAAQ/9oACAECAQE/AT//2Q==');
        self::assertIsString($jpeg);

        $info = $parser->parseJpeg($jpeg);

        self::assertSame(1, $info['w']);
        self::assertSame(1, $info['h']);
        self::assertSame('DCTDecode', $info['f']);
        self::assertArrayHasKey('data', $info);
    }

    public function test_parse_png_returns_dimensions_and_metadata(): void
    {
        $parser = new ImageParser;
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7+0mQAAAAASUVORK5CYII=');

        $info = $parser->parsePng((string) $png);

        self::assertSame(1, $info['w']);
        self::assertSame(1, $info['h']);
        self::assertSame('FlateDecode', $info['f']);
        self::assertArrayHasKey('data', $info);
    }

    public function test_parse_jpeg_throws_for_non_jpeg(): void
    {
        $parser = new ImageParser;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing or incorrect image');

        $parser->parseJpeg('not-a-jpeg');
    }

    public function test_parse_png_throws_for_invalid_signature(): void
    {
        $parser = new ImageParser;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Not a PNG image');

        $parser->parsePng('12345678invalid');
    }

    public function test_parse_png_throws_for_unknown_color_type(): void
    {
        $parser = new ImageParser;
        $png = $this->buildPng(
            width: 1,
            height: 1,
            bits: 8,
            colorType: 5,
            compressionMethod: 0,
            filterMethod: 0,
            interlaceMethod: 0,
            chunks: [$this->chunk('IEND', '')]
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unknown color type');

        $parser->parsePng($png);
    }

    public function test_parse_png_throws_when_ihdr_chunk_is_missing(): void
    {
        $parser = new ImageParser;
        $png = "\x89PNG\r\n\x1a\n";
        $png .= $this->chunk('JUNK', 'abcd');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Incorrect PNG image');

        $parser->parsePng($png);
    }

    public function test_parse_png_throws_for_16_bit_depth(): void
    {
        $parser = new ImageParser;
        $png = $this->buildPng(
            width: 1,
            height: 1,
            bits: 16,
            colorType: 2,
            compressionMethod: 0,
            filterMethod: 0,
            interlaceMethod: 0,
            chunks: [$this->chunk('IEND', '')]
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('16-bit depth not supported');

        $parser->parsePng($png);
    }

    public function test_parse_png_throws_for_unknown_compression_method(): void
    {
        $parser = new ImageParser;
        $png = $this->buildPng(
            width: 1,
            height: 1,
            bits: 8,
            colorType: 2,
            compressionMethod: 1,
            filterMethod: 0,
            interlaceMethod: 0,
            chunks: [$this->chunk('IEND', '')]
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unknown compression method');

        $parser->parsePng($png);
    }

    public function test_parse_png_throws_for_unknown_filter_method(): void
    {
        $parser = new ImageParser;
        $png = $this->buildPng(
            width: 1,
            height: 1,
            bits: 8,
            colorType: 2,
            compressionMethod: 0,
            filterMethod: 1,
            interlaceMethod: 0,
            chunks: [$this->chunk('IEND', '')]
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unknown filter method');

        $parser->parsePng($png);
    }

    public function test_parse_png_throws_for_interlaced_images(): void
    {
        $parser = new ImageParser;
        $png = $this->buildPng(
            width: 1,
            height: 1,
            bits: 8,
            colorType: 2,
            compressionMethod: 0,
            filterMethod: 0,
            interlaceMethod: 1,
            chunks: [$this->chunk('IEND', '')]
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Interlacing not supported');

        $parser->parsePng($png);
    }

    public function test_parse_png_throws_when_indexed_image_has_no_palette(): void
    {
        $parser = new ImageParser;
        $idat = (string) gzcompress("\x00\x00");
        $png = $this->buildPng(
            width: 1,
            height: 1,
            bits: 8,
            colorType: 3,
            compressionMethod: 0,
            filterMethod: 0,
            interlaceMethod: 0,
            chunks: [
                $this->chunk('IDAT', $idat),
                $this->chunk('IEND', ''),
            ]
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing palette in image');

        $parser->parsePng($png);
    }

    public function test_parse_png_parses_palette_and_transparency_for_indexed_image(): void
    {
        $parser = new ImageParser;
        $palette = "\x00\x00\x00\xFF\xFF\xFF";
        $idat = (string) gzcompress("\x00\x00");
        $png = $this->buildPng(
            width: 1,
            height: 1,
            bits: 8,
            colorType: 3,
            compressionMethod: 0,
            filterMethod: 0,
            interlaceMethod: 0,
            chunks: [
                $this->chunk('PLTE', $palette),
                $this->chunk('tRNS', "\xFF\x00"),
                $this->chunk('IDAT', $idat),
                $this->chunk('IEND', ''),
            ]
        );

        $info = $parser->parsePng($png);

        self::assertSame('Indexed', $info['cs']);
        self::assertSame($palette, $info['pal']);
        self::assertSame([1], $info['trns']);
    }

    public function test_parse_png_ignores_unknown_chunks_and_keeps_parsing(): void
    {
        $parser = new ImageParser;
        $palette = "\x00\x00\x00\xFF\xFF\xFF";
        $idat = (string) gzcompress("\x00\x00");
        $png = $this->buildPng(
            width: 1,
            height: 1,
            bits: 8,
            colorType: 3,
            compressionMethod: 0,
            filterMethod: 0,
            interlaceMethod: 0,
            chunks: [
                $this->chunk('PLTE', $palette),
                $this->chunk('JUNK', 'abc'),
                $this->chunk('IDAT', $idat),
                $this->chunk('IEND', ''),
            ]
        );

        $info = $parser->parsePng($png);

        self::assertSame('Indexed', $info['cs']);
        self::assertSame($palette, $info['pal']);
    }

    public function test_parse_png_extracts_alpha_channel_for_rgba(): void
    {
        $parser = new ImageParser;
        $rgbaScanline = "\x00\x11\x22\x33\x44";
        $idat = (string) gzcompress($rgbaScanline);
        $png = $this->buildPng(
            width: 1,
            height: 1,
            bits: 8,
            colorType: 6,
            compressionMethod: 0,
            filterMethod: 0,
            interlaceMethod: 0,
            chunks: [
                $this->chunk('IDAT', $idat),
                $this->chunk('IEND', ''),
            ]
        );

        $info = $parser->parsePng($png);

        self::assertSame('DeviceRGB', $info['cs']);
        self::assertArrayHasKey('smask', $info);
        self::assertNotFalse(gzuncompress($info['data']));
        self::assertNotFalse(gzuncompress($info['smask']));
    }

    public function test_parse_png_extracts_alpha_channel_for_gray_alpha(): void
    {
        $parser = new ImageParser;
        $grayAlphaScanline = "\x00\x11\x99";
        $idat = (string) gzcompress($grayAlphaScanline);
        $png = $this->buildPng(
            width: 1,
            height: 1,
            bits: 8,
            colorType: 4,
            compressionMethod: 0,
            filterMethod: 0,
            interlaceMethod: 0,
            chunks: [
                $this->chunk('IDAT', $idat),
                $this->chunk('IEND', ''),
            ]
        );

        $info = $parser->parsePng($png);

        self::assertSame('DeviceGray', $info['cs']);
        self::assertArrayHasKey('smask', $info);
        self::assertNotFalse(gzuncompress($info['data']));
        self::assertNotFalse(gzuncompress($info['smask']));
    }

    public function test_parse_png_throws_when_alpha_image_has_invalid_compressed_payload(): void
    {
        $parser = new ImageParser;
        $png = $this->buildPng(
            width: 1,
            height: 1,
            bits: 8,
            colorType: 6,
            compressionMethod: 0,
            filterMethod: 0,
            interlaceMethod: 0,
            chunks: [
                $this->chunk('IDAT', 'invalid-compressed-data'),
                $this->chunk('IEND', ''),
            ]
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('failed to uncompress the image');
        set_error_handler(static function (): bool {
            return true;
        });
        try {
            $parser->parsePng($png);
        } finally {
            restore_error_handler();
        }
    }

    private function buildPng(
        int $width,
        int $height,
        int $bits,
        int $colorType,
        int $compressionMethod,
        int $filterMethod,
        int $interlaceMethod,
        array $chunks
    ): string {
        $ihdrData = pack('NNCCCCC', $width, $height, $bits, $colorType, $compressionMethod, $filterMethod, $interlaceMethod);
        $png = "\x89PNG\r\n\x1a\n";
        $png .= $this->chunk('IHDR', $ihdrData);
        foreach ($chunks as $chunk) {
            $png .= $chunk;
        }

        return $png;
    }

    private function chunk(string $type, string $payload): string
    {
        return pack('N', strlen($payload)).$type.$payload."\x00\x00\x00\x00";
    }
}

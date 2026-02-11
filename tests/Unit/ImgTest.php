<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\PDFObject;
use PdfSigner\Infrastructure\PdfCore\Utils\Img;
use PHPUnit\Framework\TestCase;

final class ImgTest extends TestCase
{
    public function test_add_image_supports_base64_png(): void
    {
        $result = Img::addImage(
            $this->objectFactory(),
            $this->tinyPngBase64(),
            x: 10,
            y: 20,
            w: 50,
            h: 30
        );

        self::assertArrayHasKey('image', $result);
        self::assertArrayHasKey('command', $result);
        self::assertArrayHasKey('resources', $result);
        self::assertTrue($result['alpha']);
        self::assertStringContainsString(' Do Q', $result['command']);
    }

    public function test_add_image_supports_inline_raw_stream_with_at_prefix(): void
    {
        $pngRaw = base64_decode($this->tinyPngBase64(), true);
        self::assertIsString($pngRaw);

        $result = Img::addImage(
            $this->objectFactory(),
            '@'.$pngRaw,
            w: 10,
            h: 10
        );

        self::assertInstanceOf(PDFObject::class, $result['image']);
        self::assertFalse(str_contains((string) $result['command'], ' nan '));
    }

    public function test_add_image_throws_for_empty_name(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid image name or stream');

        Img::addImage($this->objectFactory(), '');
    }

    public function test_add_image_throws_for_unsupported_mime(): void
    {
        $invalidBase64 = base64_encode('not-an-image');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported image mime type');

        Img::addImage($this->objectFactory(), $invalidBase64);
    }

    public function test_add_image_throws_when_file_path_does_not_exist(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to read image file');

        Img::addImage($this->objectFactory(), '/tmp/image-that-does-not-exist-'.uniqid('', true).'.png');
    }

    public function test_add_image_supports_auto_dimensions_for_zero_and_negative_values(): void
    {
        $result = Img::addImage(
            $this->objectFactory(),
            $this->tinyPngBase64(),
            x: 5,
            y: 7,
            w: -96,
            h: 0,
            angle: 15,
            keepProportions: true
        );

        self::assertStringContainsString(' cm', (string) $result['command']);
        self::assertStringContainsString(' Do Q', (string) $result['command']);
    }

    public function test_create_image_objects_handles_indexed_and_smask(): void
    {
        $imageData = (string) gzcompress("\x00\x00\x00");
        $smaskData = (string) gzcompress("\x00\xFF\x00");

        $objects = Img::create_image_objects(
            [
                'w' => 1,
                'h' => 1,
                'cs' => 'Indexed',
                'bpc' => 8,
                'f' => 'FlateDecode',
                'dp' => '/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns 1',
                'pal' => "\x00\x00\x00\xFF\xFF\xFF",
                'trns' => [0],
                'data' => $imageData,
                'smask' => $smaskData,
            ],
            $this->objectFactory()
        );

        self::assertGreaterThan(2, count($objects));
        self::assertNotNull($objects[0]['SMask']);
        self::assertNotNull($objects[0]['Mask']);
        self::assertNotNull($objects[0]['DecodeParms']);
    }

    public function test_create_image_objects_sets_decode_array_for_device_cmyk(): void
    {
        $objects = Img::create_image_objects(
            [
                'w' => 1,
                'h' => 1,
                'cs' => 'DeviceCMYK',
                'bpc' => 8,
                'f' => 'FlateDecode',
                'data' => (string) gzcompress("\x00\x00\x00\x00"),
            ],
            $this->objectFactory()
        );

        self::assertNotNull($objects[0]['Decode']);
    }

    private function objectFactory(): callable
    {
        $document = new PdfDocument;

        return static fn (array $value): PDFObject => $document->createObject($value);
    }

    private function tinyPngBase64(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7+0mQAAAAASUVORK5CYII=';
    }
}

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
}

<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\PDFObject;
use PHPUnit\Framework\TestCase;

final class PdfDocumentGetObjectTest extends TestCase
{
    public function test_get_object_prefers_in_memory_version_by_default(): void
    {
        $document = new PdfDocument;
        $object = new PDFObject(1, ['Type' => '/Catalog']);
        $document->addObject($object);

        $resolved = $document->getObject(1);

        self::assertSame($object, $resolved);
    }

    public function test_get_object_returns_null_when_missing(): void
    {
        $document = new PdfDocument;

        self::assertNull($document->getObject(999));
        self::assertNull($document->getObject(999, true));
    }
}

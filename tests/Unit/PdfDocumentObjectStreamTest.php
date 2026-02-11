<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;

final class PdfDocumentObjectStreamTest extends TestCase
{
    public function test_find_object_in_object_stream_parses_target_object(): void
    {
        $objectStream = new PDFObject(99, [
            'Type' => '/ObjStm',
            'First' => 5,
            'N' => 1,
        ]);
        $objectStream->setStream('20 0 << /Type /Catalog >>');

        $document = new class($objectStream) extends PdfDocument
        {
            public function __construct(private readonly PDFObject $objectStream) {}

            public function findObject(int $oid): ?PDFObject
            {
                return $oid === $this->objectStream->getOid() ? $this->objectStream : null;
            }
        };

        $resolved = $document->findObjectInObjStm(99, 0, 20);

        self::assertSame(20, $resolved->getOid());
        self::assertSame('Catalog', $resolved['Type']->val());
    }
}

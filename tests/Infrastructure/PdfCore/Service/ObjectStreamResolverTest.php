<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Infrastructure\PdfCore\Service;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\Service\ObjectStreamResolver;

final class ObjectStreamResolverTest extends TestCase
{
    public function test_resolve_from_object_stream_accepts_whitespace_separated_index(): void
    {
        $index = "20\t0\n";
        $objectStream = new PDFObject(99, [
            'Type' => '/ObjStm',
            'First' => strlen($index),
            'N' => 1,
        ]);
        $objectStream->setStream($index.'<< /Type /Catalog >>');

        $document = new class($objectStream) extends PdfDocument
        {
            public function __construct(private readonly PDFObject $objectStream) {}

            public function findObject(int $oid): ?PDFObject
            {
                return $oid === $this->objectStream->getOid() ? $this->objectStream : null;
            }
        };

        $resolved = (new ObjectStreamResolver)->resolveFromObjectStream($document, 99, 0, 20);

        self::assertSame(20, $resolved->getOid());
        self::assertSame('Catalog', $resolved['Type']->val());
    }
}

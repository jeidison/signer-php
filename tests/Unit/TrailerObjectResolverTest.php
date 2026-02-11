<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueReference;
use SignerPHP\Infrastructure\PdfCore\Service\TrailerObjectResolver;

final class TrailerObjectResolverTest extends TestCase
{
    public function test_resolve_root_object_returns_expected_object(): void
    {
        $document = new PdfDocument;
        $root = new PDFObject(1, ['Type' => '/Catalog']);

        $document->setTrailerObject(new PDFValueObject(['Root' => new PDFValueReference(1)]));
        $document->addObject($root);

        $resolved = (new TrailerObjectResolver)->resolveRootObject($document);

        self::assertSame($root, $resolved);
    }

    public function test_resolve_root_object_throws_when_reference_is_missing(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject);

        $this->expectException(PdfCoreStructureException::class);
        $this->expectExceptionMessage('Could not find the root object from the trailer');

        (new TrailerObjectResolver)->resolveRootObject($document);
    }

    public function test_resolve_info_object_throws_when_target_object_is_missing(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject(['Info' => new PDFValueReference(10)]));

        $this->expectException(PdfCoreStructureException::class);
        $this->expectExceptionMessage('Invalid info object');

        (new TrailerObjectResolver)->resolveInfoObject($document);
    }

    public function test_resolve_root_object_throws_when_target_object_is_missing(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject(['Root' => new PDFValueReference(2)]));

        $this->expectException(PdfCoreStructureException::class);
        $this->expectExceptionMessage('Invalid root object');

        (new TrailerObjectResolver)->resolveRootObject($document);
    }
}

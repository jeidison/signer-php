<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
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
        $this->expectExceptionMessage('Invalid root object');

        (new TrailerObjectResolver)->resolveRootObject($document);
    }

    public function test_resolve_info_object_returns_null_when_target_object_is_missing(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject(['Info' => new PDFValueReference(10)]));

        $resolved = (new TrailerObjectResolver)->resolveInfoObject($document);

        self::assertNull($resolved);
    }

    public function test_resolve_info_object_returns_null_when_reference_is_missing(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject);

        $resolved = (new TrailerObjectResolver)->resolveInfoObject($document);

        self::assertNull($resolved);
    }

    public function test_resolve_info_object_returns_expected_object_when_reference_is_valid(): void
    {
        $document = new PdfDocument;
        $info = new PDFObject(10, ['Producer' => '(UnitTest)']);

        $document->setTrailerObject(new PDFValueObject(['Info' => new PDFValueReference(10)]));
        $document->addObject($info);

        $resolved = (new TrailerObjectResolver)->resolveInfoObject($document);

        self::assertSame($info, $resolved);
    }

    public function test_resolve_info_object_throws_when_reference_is_invalid(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject(['Info' => 'invalid']));

        $this->expectException(PdfCoreStructureException::class);
        $this->expectExceptionMessage('Could not find the info object from the trailer');

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

    public function test_resolve_root_object_falls_back_to_catalog_when_root_reference_is_missing(): void
    {
        $document = new PdfDocument;
        $catalog = new PDFObject(7, ['Type' => '/Catalog']);

        $document->setTrailerObject(new PDFValueObject);
        $document->addObject($catalog);

        $resolved = (new TrailerObjectResolver)->resolveRootObject($document);

        self::assertSame($catalog, $resolved);
    }

    public function test_resolve_root_object_falls_back_to_catalog_when_root_reference_is_stale(): void
    {
        $document = new PdfDocument;
        $catalog = new PDFObject(9, ['Type' => '/Catalog']);

        $document->setTrailerObject(new PDFValueObject(['Root' => new PDFValueReference(2)]));
        $document->addObject($catalog);

        $resolved = (new TrailerObjectResolver)->resolveRootObject($document);

        self::assertSame($catalog, $resolved);
    }

    public function test_resolve_root_object_skips_parsing_failures_while_scanning_xref_for_catalog(): void
    {
        $document = new class extends PdfDocument
        {
            public function __construct()
            {
                $this->setTrailerObject(new PDFValueObject(['Root' => new PDFValueReference(2)]));
            }

            public function getPdfObjects(): array
            {
                return [];
            }

            public function getXrefTable(): array
            {
                return [0 => 0, 4 => 40, 5 => 50];
            }

            public function getObject(int $oid, bool $originalVersion = false): ?PDFObject
            {
                return match ($oid) {
                    4 => throw new PdfCoreParsingException('synthetic xref parsing failure'),
                    5 => new PDFObject(5, ['Type' => '/Catalog']),
                    default => null,
                };
            }
        };

        $resolved = (new TrailerObjectResolver)->resolveRootObject($document);

        self::assertSame(5, $resolved->getOid());
    }
}

<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\PDFObject;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueReference;
use PdfSigner\Infrastructure\PdfCore\Service\DocumentTimestampObjectAssembler;
use PHPUnit\Framework\TestCase;

final class DocumentTimestampObjectAssemblerTest extends TestCase
{
    public function test_assemble_adds_doc_timestamp_reference_under_perms(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject(['Root' => new PDFValueReference(1)]));
        $document->setMaxOid(1);

        $rootObject = new PDFObject(1, ['Type' => '/Catalog']);
        $document->addObject($rootObject);

        $timestampObject = (new DocumentTimestampObjectAssembler)->assemble($document);
        $updatedRoot = $document->getObject(1);

        self::assertNotNull($updatedRoot);
        self::assertSame($timestampObject->getOid(), $updatedRoot['DocTimeStamp']->asObjectReferenceOrNull());
    }

    public function test_assemble_throws_when_root_reference_is_missing(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve root object to attach document timestamp.');

        (new DocumentTimestampObjectAssembler)->assemble($document);
    }

    public function test_assemble_throws_when_root_object_cannot_be_resolved(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(9),
        ]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid root object while attaching document timestamp.');

        (new DocumentTimestampObjectAssembler)->assemble($document);
    }

    public function test_assemble_uses_existing_perms_object_reference(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject(['Root' => new PDFValueReference(1)]));
        $rootObject = new PDFObject(1, [
            'Type' => '/Catalog',
            'Perms' => new PDFValueReference(2),
        ]);
        $permsObject = new PDFObject(2, ['Type' => '/Perms']);
        $document->addObject($rootObject);
        $document->addObject($permsObject);

        $timestampObject = (new DocumentTimestampObjectAssembler)->assemble($document);
        $updatedPerms = $document->getObject(2);

        self::assertNotNull($updatedPerms);
        self::assertSame($timestampObject->getOid(), $updatedPerms['DocTimeStamp']->asObjectReferenceOrNull());
    }

    public function test_assemble_throws_when_existing_perms_reference_is_invalid(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject(['Root' => new PDFValueReference(1)]));
        $rootObject = new PDFObject(1, [
            'Type' => '/Catalog',
            'Perms' => new PDFValueReference(77),
        ]);
        $document->addObject($rootObject);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve existing Perms object.');

        (new DocumentTimestampObjectAssembler)->assemble($document);
    }
}

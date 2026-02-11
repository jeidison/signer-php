<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\PDFObject;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueReference;
use PHPUnit\Framework\TestCase;

final class PdfDocumentCoreTest extends TestCase
{
    public function test_getters_and_setters_roundtrip_core_state(): void
    {
        $document = new PdfDocument;
        $document->setPdfVersion('PDF-1.4');
        $document->setTrailerObject(new PDFValueObject(['Size' => 1]));
        $document->setXrefPosition(123);
        $document->setXrefTable([1 => 0]);
        $document->setXrefTableVersion('1.4');
        $document->setRevisions([10, 20]);
        $document->setBufferFromString('abc');
        $document->setMaxOid(7);
        $document->setPdfObjects([1 => new PDFObject(1, ['Type' => '/Catalog'])]);

        self::assertSame('PDF-1.4', $document->getPdfVersion());
        self::assertSame(123, $document->getXrefPosition());
        self::assertSame([1 => 0], $document->getXrefTable());
        self::assertSame('1.4', $document->getXrefTableVersion());
        self::assertSame([10, 20], $document->getRevisions());
        self::assertSame('abc', $document->getBuffer()->raw());
        self::assertSame(7, $document->getMaxOid());
        self::assertCount(1, $document->getPdfObjects());
    }

    public function test_add_object_rejects_lower_generation_for_existing_oid(): void
    {
        $document = new PdfDocument;
        $higherGen = new PDFObject(1, ['Type' => '/Catalog'], 2);
        $lowerGen = new PDFObject(1, ['Type' => '/Catalog'], 1);

        self::assertTrue($document->addObject($higherGen));
        self::assertFalse($document->addObject($lowerGen));
        self::assertSame(2, $document->getObject(1)?->getGeneration());
    }

    public function test_find_object_returns_null_for_oid_zero_unknown_and_free_entry(): void
    {
        $document = new PdfDocument;
        $document->setBufferFromString('');
        $document->setXrefTable([2 => null]);

        self::assertNull($document->findObject(0));
        self::assertNull($document->findObject(1));
        self::assertNull($document->findObject(2));
    }

    public function test_find_object_resolves_direct_offset_entry(): void
    {
        $pdf = "1 0 obj\n<< /Type /Catalog >>\nendobj\n";
        $document = new PdfDocument;
        $document->setBufferFromString($pdf);
        $document->setXrefTable([1 => 0]);

        $resolved = $document->findObject(1);

        self::assertNotNull($resolved);
        self::assertSame(1, $resolved->getOid());
        self::assertSame('Catalog', $resolved['Type']->val());
    }

    public function test_find_object_routes_to_object_stream_resolution_for_stream_entries(): void
    {
        $document = new class extends PdfDocument
        {
            public bool $called = false;

            public function findObjectInObjStm(int $objstmOid, int $objpos, int $oid): PDFObject
            {
                $this->called = true;

                return new PDFObject($oid, ['Type' => '/Catalog']);
            }
        };
        $document->setBufferFromString('');
        $document->setXrefTable([5 => ['stmoid' => 9, 'pos' => 2]]);

        $resolved = $document->findObject(5);

        self::assertTrue($document->called);
        self::assertSame(5, $resolved?->getOid());
    }

    public function test_update_modify_date_updates_metadata_stream_and_info_object(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(1),
            'Info' => new PDFValueReference(3),
        ]));

        $root = new PDFObject(1, [
            'Type' => '/Catalog',
            'Metadata' => new PDFValueReference(2),
        ]);
        $metadata = new PDFObject(2, ['Type' => '/Metadata']);
        $metadata->setStream(
            '<xmp:ModifyDate>2020-01-01T00:00:00+00:00</xmp:ModifyDate>'.
            '<xmp:MetadataDate>2020-01-01T00:00:00+00:00</xmp:MetadataDate>'.
            '<xmpMM:InstanceID>uuid:old</xmpMM:InstanceID>',
            false
        );
        $info = new PDFObject(3, ['Producer' => 'old']);

        $document->addObject($root);
        $document->addObject($metadata);
        $document->addObject($info);

        $date = new \DateTime('2024-01-02T03:04:05+00:00');
        $document->updateModifyDate($date);

        $updatedMetadata = $document->getObject(2);
        $updatedInfo = $document->getObject(3);

        self::assertNotNull($updatedMetadata);
        self::assertStringContainsString('2024-01-02T03:04:05+00:00', $updatedMetadata->getStream());
        self::assertStringContainsString('uuid:', $updatedMetadata->getStream());
        self::assertNotNull($updatedInfo);
        self::assertSame('(Modifier with PHP Signer)', (string) $updatedInfo['Producer']);
        self::assertNotNull($updatedInfo['ModDate']);
    }

    public function test_update_modify_date_throws_when_metadata_reference_is_invalid(): void
    {
        $document = new PdfDocument;
        $document->setTrailerObject(new PDFValueObject([
            'Root' => new PDFValueReference(1),
            'Info' => new PDFValueReference(3),
        ]));
        $document->addObject(new PDFObject(1, [
            'Type' => '/Catalog',
            'Metadata' => new PDFValueReference(99),
        ]));
        $document->addObject(new PDFObject(3, ['Producer' => 'old']));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid metadata object');

        $document->updateModifyDate(new \DateTime('2024-01-02T03:04:05+00:00'));
    }
}

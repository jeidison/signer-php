<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\Service\ObjectStreamResolver;

final class ObjectStreamResolverTest extends TestCase
{
    public function test_resolve_from_object_stream_returns_requested_object(): void
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

        $resolver = new ObjectStreamResolver;
        $resolved = $resolver->resolveFromObjectStream($document, 99, 0, 20);

        self::assertSame(20, $resolved->getOid());
        self::assertSame('Catalog', $resolved['Type']->val());
    }

    public function test_attach_object_stream_if_present_reads_stream_payload_using_length(): void
    {
        $buffer = "1 0 obj\n<< /Length 4 >>\nstream\nDATA\nendstream\nendobj\n";
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);

        $offsetEnd = 0;
        $object = $document->objectFromString(1, 0, $offsetEnd);

        $resolver = new ObjectStreamResolver;
        $resolver->attachObjectStreamIfPresent($document, $object, $offsetEnd, 1);

        self::assertSame('DATA', $object->getStream());
    }

    public function test_resolve_from_object_stream_throws_when_stream_object_cannot_be_found(): void
    {
        $document = new class extends PdfDocument
        {
            public function findObject(int $oid): ?PDFObject
            {
                return null;
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve object stream 99');

        (new ObjectStreamResolver)->resolveFromObjectStream($document, 99, 0, 20);
    }

    public function test_resolve_from_object_stream_throws_when_extends_is_present(): void
    {
        $objectStream = new PDFObject(99, [
            'Type' => '/ObjStm',
            'First' => 5,
            'N' => 1,
            'Extends' => 1,
        ]);
        $objectStream->setStream('20 0 << /Type /Catalog >>');

        $document = new class($objectStream) extends PdfDocument
        {
            public function __construct(private readonly PDFObject $objectStream) {}

            public function findObject(int $oid): ?PDFObject
            {
                return $oid === 99 ? $this->objectStream : null;
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Extended object streams are not supported');

        (new ObjectStreamResolver)->resolveFromObjectStream($document, 99, 0, 20);
    }

    public function test_resolve_from_object_stream_throws_when_required_fields_are_missing(): void
    {
        $objectStream = new PDFObject(99, ['Type' => '/ObjStm']);
        $objectStream->setStream('');

        $document = new class($objectStream) extends PdfDocument
        {
            public function __construct(private readonly PDFObject $objectStream) {}

            public function findObject(int $oid): ?PDFObject
            {
                return $oid === 99 ? $this->objectStream : null;
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid object stream 99.');

        (new ObjectStreamResolver)->resolveFromObjectStream($document, 99, 0, 20);
    }

    public function test_resolve_from_object_stream_throws_when_type_is_not_objstm(): void
    {
        $objectStream = new PDFObject(99, [
            'Type' => '/Catalog',
            'First' => 0,
            'N' => 1,
        ]);
        $objectStream->setStream('');

        $document = new class($objectStream) extends PdfDocument
        {
            public function __construct(private readonly PDFObject $objectStream) {}

            public function findObject(int $oid): ?PDFObject
            {
                return $oid === 99 ? $this->objectStream : null;
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Object 99 is not an object stream.');

        (new ObjectStreamResolver)->resolveFromObjectStream($document, 99, 0, 20);
    }

    public function test_resolve_from_object_stream_throws_when_first_is_not_numeric(): void
    {
        $objectStream = new PDFObject(99, [
            'Type' => '/ObjStm',
            'First' => 'abc',
            'N' => 1,
        ]);
        $objectStream->setStream('20 0 << /Type /Catalog >>');

        $document = new class($objectStream) extends PdfDocument
        {
            public function __construct(private readonly PDFObject $objectStream) {}

            public function findObject(int $oid): ?PDFObject
            {
                return $oid === 99 ? $this->objectStream : null;
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid first object position in object stream 99');

        (new ObjectStreamResolver)->resolveFromObjectStream($document, 99, 0, 20);
    }

    public function test_resolve_from_object_stream_throws_for_invalid_index_pairs(): void
    {
        $objectStream = new PDFObject(99, [
            'Type' => '/ObjStm',
            'First' => 3,
            'N' => 1,
        ]);
        $objectStream->setStream('20 0');

        $document = new class($objectStream) extends PdfDocument
        {
            public function __construct(private readonly PDFObject $objectStream) {}

            public function findObject(int $oid): ?PDFObject
            {
                return $oid === 99 ? $this->objectStream : null;
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid index for object stream 99');

        (new ObjectStreamResolver)->resolveFromObjectStream($document, 99, 0, 20);
    }

    public function test_resolve_from_object_stream_throws_when_object_position_is_out_of_range(): void
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
                return $oid === 99 ? $this->objectStream : null;
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Object 20 not found in object stream 99.');

        (new ObjectStreamResolver)->resolveFromObjectStream($document, 99, 2, 20);
    }

    public function test_resolve_from_object_stream_throws_for_invalid_inner_offset(): void
    {
        $objectStream = new PDFObject(99, [
            'Type' => '/ObjStm',
            'First' => 6,
            'N' => 1,
        ]);
        $objectStream->setStream('20 -1 ABCD');

        $document = new class($objectStream) extends PdfDocument
        {
            public function __construct(private readonly PDFObject $objectStream) {}

            public function findObject(int $oid): ?PDFObject
            {
                return $oid === 99 ? $this->objectStream : null;
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid object offset inside object stream 99');

        (new ObjectStreamResolver)->resolveFromObjectStream($document, 99, 0, 20);
    }

    public function test_attach_object_stream_if_present_ignores_object_without_stream_marker(): void
    {
        $buffer = "1 0 obj\n<< /Length 4 >>\nendobj\n";
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);

        $offsetEnd = 0;
        $object = $document->objectFromString(1, 0, $offsetEnd);
        (new ObjectStreamResolver)->attachObjectStreamIfPresent($document, $object, $offsetEnd, 1);

        self::assertSame('', $object->getStream());
    }

    public function test_attach_object_stream_if_present_throws_when_length_is_missing(): void
    {
        $buffer = "1 0 obj\n<< /Type /XObject >>\nstream\nDATA\nendstream\nendobj\n";
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);

        $offsetEnd = 0;
        $object = $document->objectFromString(1, 0, $offsetEnd);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve stream length for object 1.');

        (new ObjectStreamResolver)->attachObjectStreamIfPresent($document, $object, $offsetEnd, 1);
    }

    public function test_attach_object_stream_if_present_throws_when_length_reference_is_invalid(): void
    {
        $buffer = "1 0 obj\n<< /Length [1 2] >>\nstream\nDATA\nendstream\nendobj\n";
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);

        $offsetEnd = 0;
        $object = $document->objectFromString(1, 0, $offsetEnd);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve stream length reference for object 1.');

        (new ObjectStreamResolver)->attachObjectStreamIfPresent($document, $object, $offsetEnd, 1);
    }

    public function test_attach_object_stream_if_present_throws_when_length_reference_object_is_missing(): void
    {
        $buffer = "1 0 obj\n<< /Length 2 0 R >>\nstream\nDATA\nendstream\nendobj\n";
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);
        $document->setXrefTable([1 => 0]);

        $offsetEnd = 0;
        $object = $document->objectFromString(1, 0, $offsetEnd);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve stream length object 2 for object 1.');

        (new ObjectStreamResolver)->attachObjectStreamIfPresent($document, $object, $offsetEnd, 1);
    }

    public function test_attach_object_stream_if_present_throws_when_length_reference_is_not_numeric(): void
    {
        $buffer = "1 0 obj\n<< /Length 2 0 R >>\nstream\nDATA\nendstream\nendobj\n2 0 obj\n<< /Type /Foo >>\nendobj\n";
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);
        $offsetTwo = strpos($buffer, "2 0 obj\n");
        self::assertIsInt($offsetTwo);
        $document->setXrefTable([1 => 0, 2 => $offsetTwo]);

        $offsetEnd = 0;
        $object = $document->objectFromString(1, 0, $offsetEnd);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve valid stream length for object 1.');

        (new ObjectStreamResolver)->attachObjectStreamIfPresent($document, $object, $offsetEnd, 1);
    }

    #[DataProvider('provideCorpusDerivedIndirectLengthSnippets')]
    public function test_attach_object_stream_if_present_reads_length_from_indirect_scalar_object(
        string $buffer,
        array $xrefTable,
        int $objectId,
        string $expectedStream,
    ): void {
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);
        $document->setXrefTable($xrefTable);

        $offsetEnd = 0;
        $object = $document->objectFromString($objectId, 0, $offsetEnd);
        (new ObjectStreamResolver)->attachObjectStreamIfPresent($document, $object, $offsetEnd, $objectId);

        self::assertSame($expectedStream, $object->getStream());
    }

    public static function provideCorpusDerivedIndirectLengthSnippets(): array
    {
        // Extracted from corpus file:
        // bfo-isartor-subset/PDF_A-1b/6.6 Actions/6.6.1 General/veraPDF test suite 6-6-1-t01-fail-a.pdf
        // 6 0 obj << /Type /Metadata /Subtype /XML /Length 11 0 R >> ... endobj
        // 11 0 obj 879 endobj
        $metadataLength879 = str_repeat('M', 879);
        $bufferFrom661 = "6 0 obj\n<< /Type /Metadata /Subtype /XML /Length 11 0 R >>\nstream\n{$metadataLength879}\nendstream\nendobj\n11 0 obj\n879\nendobj\n";
        $offset661Length = strpos($bufferFrom661, "11 0 obj\n");

        // Extracted from corpus file:
        // bfo-isartor-subset/PDF_A-1b/6.6 Actions/6.6.2 Trigger events/veraPDF test suite 6-6-2-t01-fail-a.pdf
        // 6 0 obj << /Type /Metadata /Subtype /XML /Length 15 0 R >> ... endobj
        // 15 0 obj 879 endobj
        $bufferFrom662 = "6 0 obj\n<< /Type /Metadata /Subtype /XML /Length 15 0 R >>\nstream\n{$metadataLength879}\nendstream\nendobj\n15 0 obj\n879\nendobj\n";
        $offset662Length = strpos($bufferFrom662, "15 0 obj\n");

        self::assertIsInt($offset661Length);
        self::assertIsInt($offset662Length);

        return [
            'corpus 6-6-1-t01-fail-a metadata length indirection' => [
                $bufferFrom661,
                [6 => 0, 11 => $offset661Length],
                6,
                $metadataLength879,
            ],
            'corpus 6-6-2-t01-fail-a metadata length indirection' => [
                $bufferFrom662,
                [6 => 0, 15 => $offset662Length],
                6,
                $metadataLength879,
            ],
        ];
    }

    public function test_attach_object_stream_if_present_reads_stream_with_crlf_marker(): void
    {
        $buffer = "1 0 obj\n<< /Length 4 >>\nstream\r\nDATA\nendstream\nendobj\n";
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);

        $offsetEnd = 0;
        $object = $document->objectFromString(1, 0, $offsetEnd);
        (new ObjectStreamResolver)->attachObjectStreamIfPresent($document, $object, $offsetEnd, 1);

        self::assertSame('DATA', $object->getStream());
    }

    #[DataProvider('provideChainsOfIndirectReferences')]
    public function test_attach_object_stream_if_present_resolves_chained_indirect_references(
        string $buffer,
        array $xrefTable,
        int $objectId,
    ): void {
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);
        $document->setXrefTable($xrefTable);

        $offsetEnd = 0;
        $object = $document->objectFromString($objectId, 0, $offsetEnd);
        (new ObjectStreamResolver)->attachObjectStreamIfPresent($document, $object, $offsetEnd, $objectId);

        self::assertSame('DATA', $object->getStream());
    }

    public static function provideChainsOfIndirectReferences(): array
    {
        // Two-level chain: 1 -> 2 -> 3 -> intValue
        // Object 2 has single key pointing to Object 3, Object 3 has single key with int value
        $buffer2 = "1 0 obj\n<< /Length 2 0 R >>\nstream\nDATA\nendstream\nendobj\n2 0 obj\n<< /Next 3 0 R >>\nendobj\n3 0 obj\n<< /Value 4 >>\nendobj\n";
        $offset2_2 = strpos($buffer2, "2 0 obj\n");
        $offset2_3 = strpos($buffer2, "3 0 obj\n");

        // Three-level chain: 1 -> 2 -> 3 -> 4 -> intValue
        // Each intermediate object has a single key pointing to next object
        $buffer3 = "1 0 obj\n<< /Length 2 0 R >>\nstream\nDATA\nendstream\nendobj\n2 0 obj\n<< /Next 3 0 R >>\nendobj\n3 0 obj\n<< /Next 4 0 R >>\nendobj\n4 0 obj\n<< /Value 4 >>\nendobj\n";
        $offset3_2 = strpos($buffer3, "2 0 obj\n");
        $offset3_3 = strpos($buffer3, "3 0 obj\n");
        $offset3_4 = strpos($buffer3, "4 0 obj\n");

        return [
            'two-level indirect reference chain' => [
                $buffer2,
                [1 => 0, 2 => $offset2_2, 3 => $offset2_3],
                1,
            ],
            'three-level indirect reference chain' => [
                $buffer3,
                [1 => 0, 2 => $offset3_2, 3 => $offset3_3, 4 => $offset3_4],
                1,
            ],
        ];
    }

    public function test_attach_object_stream_if_present_throws_on_indirect_reference_cycle(): void
    {
        // Cycle: 2 -> 3 -> 2
        $buffer = "1 0 obj\n<< /Length 2 0 R >>\nstream\nDATA\nendstream\nendobj\n2 0 obj\n3 0 R\nendobj\n3 0 obj\n2 0 R\nendobj\n";
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);
        $offset2 = strpos($buffer, "2 0 obj\n");
        $offset3 = strpos($buffer, "3 0 obj\n");
        self::assertIsInt($offset2);
        self::assertIsInt($offset3);
        $document->setXrefTable([1 => 0, 2 => $offset2, 3 => $offset3]);

        $offsetEnd = 0;
        $object = $document->objectFromString(1, 0, $offsetEnd);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve valid stream length for object 1.');

        (new ObjectStreamResolver)->attachObjectStreamIfPresent($document, $object, $offsetEnd, 1);
    }

    public function test_attach_object_stream_if_present_throws_when_indirect_object_has_multiple_keys(): void
    {
        $buffer = "1 0 obj\n<< /Length 2 0 R >>\nstream\nDATA\nendstream\nendobj\n2 0 obj\n<< /Type /Foo /Length 4 >>\nendobj\n";
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);
        $offset2 = strpos($buffer, "2 0 obj\n");
        self::assertIsInt($offset2);
        $document->setXrefTable([1 => 0, 2 => $offset2]);

        $offsetEnd = 0;
        $object = $document->objectFromString(1, 0, $offsetEnd);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve valid stream length for object 1.');

        (new ObjectStreamResolver)->attachObjectStreamIfPresent($document, $object, $offsetEnd, 1);
    }

    public function test_attach_object_stream_if_present_throws_when_indirect_chain_references_missing_object(): void
    {
        // Chain: 1 -> 2 -> 3 (missing)
        $buffer = "1 0 obj\n<< /Length 2 0 R >>\nstream\nDATA\nendstream\nendobj\n2 0 obj\n<< /Next 3 0 R >>\nendobj\n";
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);
        $offset2 = strpos($buffer, "2 0 obj\n");
        self::assertIsInt($offset2);
        $document->setXrefTable([1 => 0, 2 => $offset2]);

        $offsetEnd = 0;
        $object = $document->objectFromString(1, 0, $offsetEnd);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve valid stream length for object 1.');

        (new ObjectStreamResolver)->attachObjectStreamIfPresent($document, $object, $offsetEnd, 1);
    }

    public function test_attach_object_stream_if_present_throws_when_embedded_scalar_is_empty_object(): void
    {
        $buffer = "1 0 obj\n<< /Length 2 0 R >>\nstream\nDATA\nendstream\nendobj\n2 0 obj\n<< >>\nendobj\n";
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);
        $offset2 = strpos($buffer, "2 0 obj\n");
        self::assertIsInt($offset2);
        $document->setXrefTable([1 => 0, 2 => $offset2]);

        $offsetEnd = 0;
        $object = $document->objectFromString(1, 0, $offsetEnd);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve valid stream length for object 1.');

        (new ObjectStreamResolver)->attachObjectStreamIfPresent($document, $object, $offsetEnd, 1);
    }

    public function test_attach_object_stream_if_present_throws_when_embedded_scalar_is_invalid_reference(): void
    {
        // Object 2 contains an array (invalid scalar)
        $buffer = "1 0 obj\n<< /Length 2 0 R >>\nstream\nDATA\nendstream\nendobj\n2 0 obj\n[1 2 3]\nendobj\n";
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);
        $offset2 = strpos($buffer, "2 0 obj\n");
        self::assertIsInt($offset2);
        $document->setXrefTable([1 => 0, 2 => $offset2]);

        $offsetEnd = 0;
        $object = $document->objectFromString(1, 0, $offsetEnd);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not resolve valid stream length for object 1.');

        (new ObjectStreamResolver)->attachObjectStreamIfPresent($document, $object, $offsetEnd, 1);
    }

    public function test_attach_object_stream_if_present_detects_crlf_stream_marker_offset(): void
    {
        // Using \r\n instead of \n after "stream" keyword to test the CRLF path
        $buffer = "1 0 obj\n<< /Length 4 >>\nstream\r\nDATA\nendstream\nendobj\n";
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);

        $offsetEnd = 0;
        $object = $document->objectFromString(1, 0, $offsetEnd);
        (new ObjectStreamResolver)->attachObjectStreamIfPresent($document, $object, $offsetEnd, 1);

        self::assertSame('DATA', $object->getStream());
    }

    #[DataProvider('provideStreamMarkerOffsets')]
    public function test_attach_object_stream_if_present_handles_different_stream_markers(
        string $buffer,
        string $expectedData,
    ): void {
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);

        $offsetEnd = 0;
        $object = $document->objectFromString(1, 0, $offsetEnd);
        (new ObjectStreamResolver)->attachObjectStreamIfPresent($document, $object, $offsetEnd, 1);

        self::assertSame($expectedData, $object->getStream());
    }

    public static function provideStreamMarkerOffsets(): array
    {
        return [
            'LF stream marker' => [
                "1 0 obj\n<< /Length 4 >>\nstream\nDATA\nendstream\nendobj\n",
                'DATA',
            ],
            'CRLF stream marker' => [
                "1 0 obj\n<< /Length 4 >>\nstream\r\nDATA\nendstream\nendobj\n",
                'DATA',
            ],
        ];
    }

    public function test_attach_object_stream_if_present_ignores_stream_without_marker(): void
    {
        // Object has stream keyword but no valid marker (\n or \r\n) after stream
        $buffer = "1 0 obj\n<< /Length 4 >>\nstream  INVALID\nendstream\nendobj\n";
        $document = new PdfDocument;
        $document->setBufferFromString($buffer);

        $offsetEnd = 0;
        $object = $document->objectFromString(1, 0, $offsetEnd);
        (new ObjectStreamResolver)->attachObjectStreamIfPresent($document, $object, $offsetEnd, 1);

        // When no valid stream marker is found, attachObjectStreamIfPresent returns early with empty stream
        self::assertSame('', $object->getStream());
    }
}

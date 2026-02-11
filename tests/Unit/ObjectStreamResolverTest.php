<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\PDFObject;
use PdfSigner\Infrastructure\PdfCore\Service\ObjectStreamResolver;
use PHPUnit\Framework\TestCase;

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
}

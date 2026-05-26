<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueList;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use SignerPHP\Infrastructure\PdfCore\Xref\Service\XRef15Parser;

final class XRef15ParserTest extends TestCase
{
    public function test_parse_reads_offset_entry_type_one(): void
    {
        $stream = chr(1).pack('n', 123).chr(0);
        $object = $this->xrefObject($stream, [1, 2, 1], [5, 1], 10);
        $document = $this->documentAtPositions([100 => $object]);

        $result = (new XRef15Parser)->parse($document, 100);

        self::assertSame(123, $result->table[5]);
    }

    public function test_parse_reads_object_stream_entry_type_two(): void
    {
        $stream = chr(2).pack('n', 9).chr(4);
        $object = $this->xrefObject($stream, [1, 2, 1], [8, 1], 20);
        $document = $this->documentAtPositions([100 => $object]);

        $result = (new XRef15Parser)->parse($document, 100);

        self::assertSame(['stmoid' => 9, 'pos' => 4], $result->table[8]);
    }

    public function test_parse_reads_free_entry_type_zero(): void
    {
        $stream = chr(0).pack('n', 0).chr(0);
        $object = $this->xrefObject($stream, [1, 2, 1], [2, 1], 10);
        $document = $this->documentAtPositions([100 => $object]);

        $result = (new XRef15Parser)->parse($document, 100);

        self::assertNull($result->table[2]);
    }

    public function test_parse_merges_prev_table(): void
    {
        $prevObject = $this->xrefObject(chr(1).pack('n', 33).chr(0), [1, 2, 1], [1, 1], 10);
        $currentObject = $this->xrefObject(chr(1).pack('n', 77).chr(0), [1, 2, 1], [2, 1], 10);
        $currentObject['Prev'] = 50;

        $document = $this->documentAtPositions([
            50 => $prevObject,
            100 => $currentObject,
        ]);

        $result = (new XRef15Parser)->parse($document, 100);

        self::assertSame(33, $result->table[1]);
        self::assertSame(77, $result->table[2]);
    }

    public function test_parse_keeps_offset_entry_when_generation_is_non_zero_for_type_one(): void
    {
        $stream = chr(1).pack('n', 123).chr(1);
        $object = $this->xrefObject($stream, [1, 2, 1], [5, 1], 10);
        $document = $this->documentAtPositions([100 => $object]);

        $result = (new XRef15Parser)->parse($document, 100);

        self::assertSame(123, $result->table[5]);
    }

    public function test_parse_throws_for_invalid_entry_type(): void
    {
        $stream = chr(3).pack('n', 0).chr(0);
        $object = $this->xrefObject($stream, [1, 2, 1], [5, 1], 10);
        $document = $this->documentAtPositions([100 => $object]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid stream for xref table');

        (new XRef15Parser)->parse($document, 100);
    }

    public function test_parse_throws_for_invalid_index_ranges(): void
    {
        $object = $this->xrefObject(chr(1).pack('n', 10).chr(0), [1, 2, 1], [1, 2, 3], 10);
        $document = $this->documentAtPositions([100 => $object]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid indexes of xref table');

        (new XRef15Parser)->parse($document, 100);
    }

    public function test_parse_throws_for_invalid_prev_reference(): void
    {
        $object = $this->xrefObject(chr(1).pack('n', 10).chr(0), [1, 2, 1], [1, 1], 10);
        $object['Prev'] = '/Invalid';
        $document = $this->documentAtPositions([100 => $object]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid reference to a previous xref table');

        (new XRef15Parser)->parse($document, 100);
    }

    public function test_parse_throws_for_invalid_w_field_count(): void
    {
        $object = $this->xrefObject(chr(1).pack('n', 10).chr(0), [1, 2], [1, 1], 10);
        $document = $this->documentAtPositions([100 => $object]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid cross reference object');

        (new XRef15Parser)->parse($document, 100);
    }

    public function test_parse_throws_when_size_is_not_numeric(): void
    {
        $object = $this->xrefObject(chr(1).pack('n', 10).chr(0), [1, 2, 1], [1, 1], 10);
        $object['Size'] = 'abc';
        $document = $this->documentAtPositions([100 => $object]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not get the size of the xref table');

        (new XRef15Parser)->parse($document, 100);
    }

    public function test_decode_unsigned_int_returns_zero_for_zero_width(): void
    {
        $parser = new XRef15Parser;
        $method = new \ReflectionMethod($parser, 'decodeUnsignedInt');
        $method->setAccessible(true);

        $value = $method->invoke($parser, '', 0);

        self::assertSame(0, $value);
    }

    // ----------------------------------------------------------------
    // Corpus hardening regression tests (problem #4: invalid xref stream)
    // ----------------------------------------------------------------

    /**
     * Problem #4 (6 corpus cases): /Prev in a 1.5 xref stream points to a classic
     * xref table, but the bytes at that offset look like `N G obj` (object header).
     * parsePreviousTable() currently treats any `\d+ \d+ obj` prefix as an xref
     * stream and calls XRef15Parser::parse(), which throws when the object is not
     * a valid xref stream.  The exception propagates instead of falling back.
     *
     * Reproduce: /Prev 50, buffer at 50 starts with `1 0 obj` (triggers the xref
     * stream branch) but objectFromString() throws at that offset.
     * Expected: parse() returns successfully using the XRef14 fallback path instead.
     */
    public function test_parse_falls_back_to_xref14_when_prev_is_classic_table_despite_obj_header_prefix(): void
    {
        // Classic xref table sitting right after a `1 0 obj` artefact at offset 0.
        // parsePreviousTable() will see `1 0 obj` and try XRef15 first; it must
        // fall back to XRef14 when that attempt fails.
        $classicXrefBuffer = "1 0 obj\n" // artefact: looks like an obj header
            ."xref\n0 2\n"
            ."0000000000 65535 f\n"
            ."0000000060 00000 n\n"
            ."trailer << /Size 2 /Root 1 0 R >>\n";

        $prevPosition = 0; // /Prev points here (starts with `1 0 obj`)

        // Current xref stream at position 100
        $currentObject = $this->xrefObject(
            chr(1).pack('n', 77).chr(0), // one type-1 entry: oid 2 → offset 77
            [1, 2, 1],
            [2, 1],
            10
        );
        $currentObject['Prev'] = $prevPosition;

        // The document stub: objectFromString() throws for offset 0 (not a real xref stream)
        $document = new class($currentObject, $classicXrefBuffer) extends PdfDocument {
            public function __construct(
                private readonly PDFObject $current,
                string $buffer
            ) {
                $this->setBufferFromString($buffer);
            }

            public function objectFromString(int|string|null $expectedObjId, int $offset = 0, int &$offsetEnd = 0): PDFObject
            {
                if ($offset === 0) {
                    throw new \Exception('Not a valid xref stream object at offset 0');
                }

                return $this->current;
            }

            public function findObjectAtOffset(int $objectOffset, ?int $expectedOid = null): PDFObject
            {
                return $this->current;
            }
        };

        // Build a buffer that triggers the numeric-prefix detection:
        // offset 100 = where the current xref object is placed in the mock.
        $result = (new XRef15Parser)->parse($document, 100);

        // Entries from the current stream must be present
        self::assertSame(77, $result->table[2]);
        // Entries from the /Prev classic table should be merged in via the XRef14 fallback
        self::assertArrayHasKey(1, $result->table);
    }

    private function xrefObject(string $stream, array $w, array $index, int $size): PDFObject
    {
        $wValues = array_map(static fn (int $v): PDFValueSimple => new PDFValueSimple($v), $w);
        $indexValues = array_map(static fn (int $v): PDFValueSimple => new PDFValueSimple($v), $index);

        $object = new PDFObject(1, [
            'Type' => '/XRef',
            'W' => new PDFValueList($wValues),
            'Index' => new PDFValueList($indexValues),
            'Size' => $size,
        ]);
        $object->setStream($stream);

        return $object;
    }

    /** @param array<int, PDFObject> $objectsByPosition */
    private function documentAtPositions(array $objectsByPosition, string $buffer = ''): PdfDocument
    {
        if ($buffer === '') {
            ksort($objectsByPosition);
            $buffer = '';
            $cursor = 0;
            foreach (array_keys($objectsByPosition) as $offset) {
                if ($offset > $cursor) {
                    $buffer .= str_repeat(' ', $offset - $cursor);
                }
                $buffer .= '1 0 obj';
                $cursor = strlen($buffer);
            }
        }

        return new class($objectsByPosition, $buffer) extends PdfDocument
        {
            /** @param array<int, PDFObject> $objectsByPosition */
            public function __construct(private readonly array $objectsByPosition, string $buffer)
            {
                $this->setBufferFromString($buffer);
            }

            public function objectFromString(int|string|null $expectedObjId, int $offset = 0, int &$offsetEnd = 0): PDFObject
            {
                return $this->objectsByPosition[$offset];
            }

            public function findObjectAtOffset(int $objectOffset, ?int $expectedOid = null): PDFObject
            {
                return $this->objectsByPosition[$objectOffset];
            }
        };
    }
}

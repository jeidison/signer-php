<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Infrastructure\PdfCore\Xref\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueList;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use SignerPHP\Infrastructure\PdfCore\Xref\Service\XRef15Parser;

final class XRef15ParserTest extends TestCase
{
    public function test_parse_stops_on_circular_prev_reference(): void
    {
        $stream = chr(1).pack('n', 123).chr(0);
        $object = $this->xrefObject($stream, [1, 2, 1], [5, 1], 10);
        $object['Prev'] = 100;

        $document = $this->documentAtPositions([100 => $object]);

        $result = (new XRef15Parser)->parse($document, 100);

        self::assertSame(123, $result->table[5]);
    }

    #[DataProvider('previousTableMergeCases')]
    public function test_parse_merges_previous_tables_and_keeps_expected_entries(
        string $previousKind,
        int $currentOffset,
        int $currentObjectId,
        int $currentEntryOffset,
        int $previousOffset,
        int $previousObjectId,
        int $previousEntryOffset,
        int $expectedCurrentOffset
    ): void {
        $current = $this->xrefObject(chr(1).pack('n', $currentEntryOffset).chr(0), [1, 2, 1], [$currentObjectId, 1], 8);
        $current['Prev'] = $previousOffset;

        if ($previousKind === 'classic') {
            $classicXref = sprintf(
                "xref\n%d 1\n%010d 00000 n \ntrailer\n<< /Size 8 >>\nstartxref\n0\n%%EOF\n",
                $previousObjectId,
                $previousEntryOffset
            );

            $document = $this->documentAtPositions([
                $currentOffset => $current,
            ], $classicXref.str_repeat(' ', max(0, $currentOffset - strlen($classicXref))).'2 0 obj');
        } else {
            $previous = $this->xrefObject(chr(1).pack('n', $previousEntryOffset).chr(0), [1, 2, 1], [$previousObjectId, 1], 8);
            $document = $this->documentAtPositions([
                $currentOffset => $current,
                $previousOffset => $previous,
            ]);
        }

        $result = (new XRef15Parser)->parse($document, $currentOffset);

        if ($previousObjectId !== $currentObjectId) {
            self::assertSame($previousEntryOffset, $result->table[$previousObjectId]);
        }
        self::assertSame($expectedCurrentOffset, $result->table[$currentObjectId]);
    }

    /** @return array<string, array{string, int, int, int, int, int, int, int}> */
    public static function previousTableMergeCases(): array
    {
        return [
            'merges previous classic xref table' => ['classic', 100, 2, 77, 0, 1, 33, 77],
            'merges previous xref stream table' => ['stream', 100, 2, 77, 200, 1, 33, 77],
            'current entry overrides same object from previous table' => ['stream', 100, 2, 77, 200, 2, 33, 77],
            'current entry overrides same object from previous classic table' => ['classic', 100, 2, 77, 0, 2, 33, 77],
        ];
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

            public function findObjectAtOffset(int $objectOffset, ?int $expectedOid = null): PDFObject
            {
                return $this->objectsByPosition[$objectOffset];
            }
        };
    }
}

<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Infrastructure\PdfCore\Xref\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\Xref\Service\XRef14Parser;

final class XRef14ParserTest extends TestCase
{
    #[DataProvider('parseFailureCases')]
    public function test_parse_throws_for_invalid_xref_inputs(string $buffer, int $xrefPosition, string $expectedMessage): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($expectedMessage);

        (new XRef14Parser)->parse($buffer, $xrefPosition);
    }

    #[DataProvider('parseSuccessCases')]
    public function test_parse_handles_supported_xref_variants(
        string $buffer,
        int $xrefPosition,
        int $expectedObjectId,
        ?int $expectedOffset
    ): void {
        $result = (new XRef14Parser)->parse($buffer, $xrefPosition);

        if ($expectedOffset === null) {
            self::assertSame([], $result->table);

            return;
        }

        self::assertSame($expectedOffset, $result->table[$expectedObjectId]);
    }

    /** @return array<string, array{string, int, int, int|null}> */
    public static function parseSuccessCases(): array
    {
        $longXrefEntries = '';
        for ($i = 0; $i < 80; $i++) {
            $longXrefEntries .= sprintf("%010d 00000 n \n", 10 + $i);
        }

        $longXrefTable = "xref\n0 80\n".$longXrefEntries;
        $longXrefBuffer = $longXrefTable."trailer\n<< /Size 80 >>\nstartxref\n0\n%%EOF\n";
        $longXrefPositionInsideBody = strpos($longXrefBuffer, '0000000079 00000 n');
        if ($longXrefPositionInsideBody === false) {
            throw new \RuntimeException('Failed to build long xref regression fixture.');
        }

        return [
            'prefixed chunk ending with xref keyword' => [
                "endobj xref\n0 1\n0000000010 00000 n \ntrailer\n<< /Size 1 >>\nstartxref\n0\n%%EOF\n",
                0,
                0,
                10,
            ],
            'skip non keyword xref substring and use valid tag' => [
                "prefix-xref-tag xref\n0 1\n0000000042 00000 n \ntrailer\n<< /Size 1 >>\nstartxref\n0\n%%EOF\n",
                0,
                0,
                42,
            ],
            'accept empty zero-zero section' => [
                "xref\n0 0\ntrailer\n<< /Size 1 >>\nstartxref\n0\n%%EOF\n",
                0,
                0,
                null,
            ],
            'stop on circular prev chain' => [
                "xref\n0 1\n0000000010 00000 n \ntrailer\n<< /Size 1 /Prev 0 >>\nstartxref\n0\n%%EOF\n",
                0,
                0,
                10,
            ],
            'section header with extra spacing still parses' => [
                "xref\n  0   1 \n0000000015 00000 n \ntrailer\n<< /Size 1 >>\nstartxref\n0\n%%EOF\n",
                0,
                0,
                15,
            ],
            'xref position beyond EOF recovered by backward scan' => [
                // startxref offset is stale/too large; backward scan locates the real xref table.
                "xref\n0 1\n0000000010 00000 n \ntrailer\n<< /Size 1 >>\nstartxref\n99999\n%%EOF\n",
                99999,
                0,
                10,
            ],
            'xref position beyond EOF recovered by newline-prefixed scan marker' => [
                // Synthetic fixture from malformed corpus: xref is preceded by a newline and
                // fallback must return the position after that newline (pos + 1).
                "%PDF-1.4\nxref\n0 1\n0000000010 00000 n \ntrailer\n<< /Size 1 >>\nstartxref\n99999\n%%EOF\n",
                99999,
                0,
                10,
            ],
            'startxref offset points past xref keyword into table body (backward expansion)' => [
                // Offset 5 is inside the xref table (after the "xref\n" keyword line at 0).
                // The backward-expanded window (max 512 bytes) covers position 0 and finds it.
                "xref\n0 1\n0000000010 00000 n \ntrailer\n<< /Size 1 >>\nstartxref\n5\n%%EOF\n",
                5,
                0,
                10,
            ],
            'startxref offset deep inside long xref table is recovered by fallback scan' => [
                // Regression from corpus: startxref may point into a large xref body far from the
                // keyword. The parser must still resolve the correct table header.
                $longXrefBuffer,
                $longXrefPositionInsideBody,
                79,
                89,
            ],
        ];
    }

    /** @return array<string, array{string, int, string}> */
    public static function parseFailureCases(): array
    {
        return [
            'xref position beyond EOF and no xref table in buffer' => [
                // No 'xref' keyword at all → backward scan finds nothing → throws.
                "%PDF-1.4\nno xref content here\ntrailer\n<< /Size 1 >>\n",
                9999,
                'beyond end of file',
            ],
            'xref tag missing at provided position' => [
                "no-tag-here\ntrailer\n<< /Size 1 >>\nstartxref\n0\n%%EOF\n",
                0,
                'Xref tag not found at position 0',
            ],
            'malformed xref rethrows without fallback retry' => [
                // Synthetic fixture from problematic PDFs: xref section contains no valid header.
                // parseEntries throws "Malformed xref...", and the catch block must rethrow
                // immediately (non "Xref tag not found" message).
                "xref\nnot-a-section-or-entry\ntrailer\n<< /Size 1 >>\nstartxref\n0\n%%EOF\n",
                0,
                'Malformed xref at position 0',
            ],
            'fallback retry with trailer at zero cannot scan before offset' => [
                // Trailer at position 0 makes findLastStandaloneXrefBefore receive beforeOffset=0,
                // which returns null and rethrows the original xref-tag-not-found error.
                "trailer\n<< /Size 1 >>\nstartxref\n0\n%%EOF\n",
                0,
                'Xref tag not found at position 0',
            ],
        ];
    }
}

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
        ];
    }

    /** @return array<string, array{string, int, string}> */
    public static function parseFailureCases(): array
    {
        $validBuffer = "xref\n0 1\n0000000010 00000 n \ntrailer\n<< /Size 1 >>\n";

        return [
            'xref position beyond end of file' => [$validBuffer, strlen($validBuffer) + 1, 'beyond end of file'],
            'xref tag missing at provided position' => [
                "no-tag-here\ntrailer\n<< /Size 1 >>\nstartxref\n0\n%%EOF\n",
                0,
                'Xref tag not found at position 0',
            ],
        ];
    }
}

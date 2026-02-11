<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\Xref\Service\XRef14Parser;
use PHPUnit\Framework\TestCase;

final class XRef14ParserTest extends TestCase
{
    public function test_parse_merges_previous_xref_table_when_prev_is_defined(): void
    {
        $first = "xref\n0 2\n0000000000 65535 f \n0000000010 00000 n \ntrailer\n<< /Size 2 >>\nstartxref\n0\n%%EOF\n";
        $second = "xref\n2 1\n0000000020 00000 n \ntrailer\n<< /Size 3 /Prev 0 >>\nstartxref\n123\n%%EOF\n";
        $buffer = $first.$second;
        $secondPos = strlen($first);

        $result = (new XRef14Parser)->parse($buffer, $secondPos);

        self::assertSame(10, $result->table[1]);
        self::assertSame(20, $result->table[2]);
        self::assertSame('1.4', $result->minimumPdfVersion);
    }

    public function test_parse_throws_when_xref_tag_is_missing_at_position(): void
    {
        $buffer = "notxref\ntrailer\n<< /Size 1 >>\nstartxref\n0\n%%EOF\n";

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Xref tag not found at position 0');
        (new XRef14Parser)->parse($buffer, 0);
    }

    public function test_parse_throws_when_section_header_appears_before_consuming_entries(): void
    {
        $buffer = "xref\n0 2\n1 1\n0000000001 00000 n \ntrailer\n<< /Size 2 >>\nstartxref\n0\n%%EOF\n";

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Malformed xref at position 0');
        (new XRef14Parser)->parse($buffer, 0);
    }

    public function test_parse_throws_when_entry_appears_without_open_section(): void
    {
        $buffer = "xref\n0 0\n0000000001 00000 n \ntrailer\n<< /Size 1 >>\nstartxref\n0\n%%EOF\n";

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unexpected entry for xref');
        (new XRef14Parser)->parse($buffer, 0);
    }

    public function test_parse_throws_for_non_zero_generation_in_in_use_entry(): void
    {
        $buffer = "xref\n1 1\n0000000001 00001 n \ntrailer\n<< /Size 2 >>\nstartxref\n0\n%%EOF\n";

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Objects of non-zero generation are not supported.');
        (new XRef14Parser)->parse($buffer, 0);
    }

    public function test_parse_throws_when_prev_is_not_numeric(): void
    {
        $buffer = "xref\n0 1\n0000000001 00000 n \ntrailer\n<< /Size 1 /Prev /ABC >>\nstartxref\n0\n%%EOF\n";

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid trailer: Prev must be numeric.');
        (new XRef14Parser)->parse($buffer, 0);
    }

    public function test_parse_marks_non_zero_offset_free_entries_as_null(): void
    {
        $buffer = "xref\n1 1\n0000000010 00000 f \ntrailer\n<< /Size 2 >>\nstartxref\n0\n%%EOF\n";

        $result = (new XRef14Parser)->parse($buffer, 0);

        self::assertArrayHasKey(1, $result->table);
        self::assertNull($result->table[1]);
    }

    public function test_parse_ignores_lines_that_do_not_match_entry_pattern(): void
    {
        $buffer = "xref\n1 1\nthis-is-not-an-entry\n0000000010 00000 n \ntrailer\n<< /Size 2 >>\nstartxref\n0\n%%EOF\n";

        $result = (new XRef14Parser)->parse($buffer, 0);

        self::assertSame(10, $result->table[1]);
    }
}

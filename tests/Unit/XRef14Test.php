<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\Xref\XRef14;

final class XRef14Test extends TestCase
{
    public function test_get_xref_result_fails_when_trailer_tag_is_missing(): void
    {
        $xref = XRef14::new()
            ->withBuffer("xref\n0 1\n0000000000 65535 f \n")
            ->withXrefPosition(0);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Trailer tag not found after xref at position 0');
        $xref->parse();
    }

    public function test_to_legacy_tuple_returns_parsed_structure(): void
    {
        $buffer = "xref\n0 2\n0000000000 65535 f \n0000000010 00000 n \ntrailer\n<< /Size 2 >>\nstartxref\n0\n%%EOF\n";
        $result = XRef14::new()
            ->withBuffer($buffer)
            ->withXrefPosition(0)
            ->toLegacyTuple();

        self::assertIsArray($result);
        self::assertSame(10, $result[0][1]);
        self::assertSame('1.4', $result[2]);
    }
}

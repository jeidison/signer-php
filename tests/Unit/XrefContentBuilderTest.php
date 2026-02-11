<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\Xref\Service\XrefContentBuilder;

final class XrefContentBuilderTest extends TestCase
{
    public function test_build_xref14_produces_classic_header(): void
    {
        $builder = new XrefContentBuilder;

        $content = $builder->buildXref14([
            0 => 0,
            1 => 15,
            2 => 30,
        ]);

        self::assertStringStartsWith("xref\n", $content);
        self::assertStringContainsString('0000000015 00000 n', $content);
        self::assertStringContainsString('0000000030 00000 n', $content);
    }

    public function test_build_xref15_produces_index_and_stream(): void
    {
        $builder = new XrefContentBuilder;

        $result = $builder->buildXref15([
            0 => 0,
            1 => 15,
            2 => ['stmoid' => 9, 'pos' => 1],
        ]);

        self::assertSame([1, 4, 1], $result['W']);
        self::assertSame('1 2', $result['Index']);
        self::assertNotSame('', $result['stream']);
    }

    public function test_build_xref15_handles_discontinuous_ranges_and_null_entries(): void
    {
        $builder = new XrefContentBuilder;

        $result = $builder->buildXref15([
            0 => 0,
            2 => 30,
            4 => null,
            5 => ['stmoid' => 9, 'pos' => 2],
        ]);

        self::assertSame('2 1 4 2', $result['Index']);
        self::assertSame(18, strlen($result['stream']));
    }
}

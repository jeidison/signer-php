<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\Buffer;

final class BufferTest extends TestCase
{
    public function test_data_appends_segments_and_updates_size(): void
    {
        $buffer = new Buffer('ab');
        $buffer->data('cd', 'ef');

        self::assertSame(6, $buffer->size());
        self::assertSame('abcdef', $buffer->raw());
        self::assertSame('abcdef', (string) $buffer);
    }

    public function test_append_and_add_keep_original_behavior(): void
    {
        $a = new Buffer('A');
        $b = new Buffer('B');
        $c = new Buffer('C');

        $a->append($b);
        self::assertSame('AB', $a->raw());

        $combined = $a->add($c);
        self::assertSame('AB', $a->raw());
        self::assertSame('ABC', $combined->raw());
    }
}

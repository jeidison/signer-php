<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\StreamReader;
use PHPUnit\Framework\TestCase;

final class StreamReaderTest extends TestCase
{
    public function test_navigation_and_substring_behaviors(): void
    {
        $reader = new StreamReader('abcdef', 2);

        self::assertSame(6, $reader->size());
        self::assertSame(2, $reader->getPosition());
        self::assertSame('c', $reader->currentChar());
        self::assertSame('d', $reader->nextChar());
        self::assertSame('def', $reader->nextChars(10));
        self::assertTrue($reader->eos());
        self::assertFalse($reader->currentChar());
    }

    public function test_goto_bounds_and_substr_without_length(): void
    {
        $reader = new StreamReader('abcdef');
        $reader->goto(-10);
        self::assertSame(0, $reader->getPosition());

        $reader->goto(3);
        self::assertSame('def', $reader->subStrAtPos());
        self::assertSame('de', $reader->subStrAtPos(2));

        $reader->goto(99);
        self::assertSame(6, $reader->getPosition());
        self::assertSame('', $reader->subStrAtPos());
    }
}

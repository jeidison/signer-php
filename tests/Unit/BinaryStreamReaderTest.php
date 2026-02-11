<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use Exception;
use PdfSigner\Infrastructure\PdfCore\StreamReader;
use PdfSigner\Infrastructure\PdfCore\Utils\BinaryStreamReader;
use PHPUnit\Framework\TestCase;

final class BinaryStreamReaderTest extends TestCase
{
    public function test_read_and_read_int(): void
    {
        $reader = new BinaryStreamReader;
        $stream = new StreamReader("ABCD\x00\x00\x00\x2A");

        self::assertSame('ABCD', $reader->read($stream, 4));
        self::assertSame(42, $reader->readInt($stream));
    }

    public function test_read_throws_on_unexpected_end(): void
    {
        $reader = new BinaryStreamReader;
        $stream = new StreamReader('A');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unexpected end of stream');

        $reader->read($stream, 2);
    }
}

<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\Utils\Str;

final class StrTest extends TestCase
{
    public function test_is_base64_detects_valid_and_invalid_values(): void
    {
        self::assertTrue(Str::isBase64(base64_encode('abc')));
        self::assertFalse(Str::isBase64('not-base64'));
        self::assertFalse(Str::isBase64(''));
        self::assertFalse(Str::isBase64('YQ'));
    }

    public function test_random_throws_when_length_is_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Str::random(0);
    }

    public function test_random_returns_expected_length(): void
    {
        self::assertSame(12, strlen(Str::random(12)));
        self::assertSame(10, strlen(Str::random(10, true)));
        self::assertSame(9, strlen(Str::random(9, true, true)));
    }
}

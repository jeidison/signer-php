<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\Utils\Mime;
use PHPUnit\Framework\TestCase;

final class MimeTest extends TestCase
{
    public function test_mime_to_ext_returns_known_extensions(): void
    {
        self::assertSame('png', Mime::mimeToExt('image/png'));
        self::assertSame('jpg', Mime::mimeToExt('image/jpeg'));
        self::assertSame('pdf', Mime::mimeToExt('application/pdf'));
    }

    public function test_mime_to_ext_returns_null_for_unknown_mime(): void
    {
        self::assertNull(Mime::mimeToExt('application/x-unknown-type'));
    }
}

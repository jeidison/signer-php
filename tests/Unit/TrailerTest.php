<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\Trailer;
use PHPUnit\Framework\TestCase;

final class TrailerTest extends TestCase
{
    public function test_get_trailer_parses_dictionary_between_trailer_and_startxref(): void
    {
        $buffer = "xref\n0 1\n0000000000 65535 f \ntrailer\n<< /Size 1 >>\nstartxref\n9\n%%EOF\n";
        $trailer = Trailer::new()
            ->withBuffer($buffer)
            ->withTrailerPosition((int) strpos($buffer, 'trailer'))
            ->getTrailer();

        self::assertSame('1', (string) $trailer['Size']);
    }

    public function test_get_trailer_throws_when_section_is_missing(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Trailer not found.');

        Trailer::new()
            ->withBuffer('%PDF-1.4 no trailer')
            ->withTrailerPosition(0)
            ->getTrailer();
    }

    public function test_get_trailer_throws_when_trailer_dictionary_is_invalid(): void
    {
        $buffer = "xref\n0 1\n0000000000 65535 f \ntrailer\n<< /Size >>\nstartxref\n9\n%%EOF\n";

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Trailer is not valid.');

        Trailer::new()
            ->withBuffer($buffer)
            ->withTrailerPosition((int) strpos($buffer, 'trailer'))
            ->getTrailer();
    }
}

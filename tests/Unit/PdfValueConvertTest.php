<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use PHPUnit\Framework\TestCase;

final class PdfValueConvertTest extends TestCase
{
    public function test_string_conversion_handles_empty_string(): void
    {
        $object = new PDFValueObject([
            'Empty' => '',
        ]);

        self::assertStringContainsString('/Empty', (string) $object);
    }
}

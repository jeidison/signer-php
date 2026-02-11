<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;

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

<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\SignatureAppearance;

final class SignatureAppearanceStateTest extends TestCase
{
    public function test_with_rect_requires_exactly_four_coordinates(): void
    {
        $appearance = SignatureAppearance::new();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Signature rectangle must contain exactly 4 coordinates.');
        $appearance->withRect([0, 0, 100]);
    }
}

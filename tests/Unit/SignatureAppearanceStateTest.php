<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\SignatureAppearance;
use PHPUnit\Framework\TestCase;

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

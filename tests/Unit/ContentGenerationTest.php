<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\Utils\ContentGeneration;
use PHPUnit\Framework\TestCase;

final class ContentGenerationTest extends TestCase
{
    public function test_tx_and_sx_format_transform_commands(): void
    {
        self::assertSame(' 1 0 0 1 10.00 20.50 cm', ContentGeneration::tx(10, 20.5));
        self::assertSame(' 100.00 0 0 200.00 0 0 cm', ContentGeneration::sx(100, 200));
    }

    public function test_deg2rad_and_rx_generate_rotation_values(): void
    {
        self::assertEqualsWithDelta(M_PI / 2, ContentGeneration::deg2rad(90), 0.00001);

        $matrix = ContentGeneration::rx(90);
        self::assertStringContainsString('0.00 1.00 -1.00 0.00', $matrix);
    }
}

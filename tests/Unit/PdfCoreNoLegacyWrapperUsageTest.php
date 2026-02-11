<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PdfCoreNoLegacyWrapperUsageTest extends TestCase
{
    public function test_core_uses_optional_apis_instead_of_legacy_wrappers(): void
    {
        $corePath = __DIR__.'/../../src/Infrastructure/PdfCore';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($corePath));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            if (str_ends_with($path, 'Compat/LegacyPdfValueCompat.php') || str_ends_with($path, 'PdfValue/PDFValue.php')) {
                continue;
            }

            $content = (string) file_get_contents($path);
            self::assertStringNotContainsString('->getInt(', $content, $path);
            self::assertStringNotContainsString('->getObjectReferenced(', $content, $path);
        }
    }
}

<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Service;

use PdfSigner\Application\DTO\SignatureAppearanceDto;
use PdfSigner\Infrastructure\Native\Contract\DefaultSignatureAppearanceProviderInterface;

final class DefaultSignatureAppearanceProvider implements DefaultSignatureAppearanceProviderInterface
{
    /**
     * Fallback 1x1 PNG pixel for environments where asset lookup fails.
     */
    private const DEFAULT_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/aX8AAAAASUVORK5CYII=';

    public function makeDefault(): SignatureAppearanceDto
    {
        return new SignatureAppearanceDto(
            imagePath: $this->resolveDefaultImagePath(),
            rect: [36, 36, 276, 120],
            page: 0,
        );
    }

    private function resolveDefaultImagePath(): string
    {
        $assetPath = __DIR__.'/../Assets/default-signature-stamp.png';
        if (is_file($assetPath)) {
            return $assetPath;
        }

        return self::DEFAULT_PNG_BASE64;
    }
}

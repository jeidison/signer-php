<?php

declare(strict_types=1);

namespace PdfSigner\Application\DTO;

enum CertificationLevel: int
{
    case NoChangesAllowed = 1;
    case FormFillAndSignatures = 2;
    case FormFillSignaturesAndAnnotations = 3;

    public static function fromInt(int $level): ?self
    {
        return match ($level) {
            1 => self::NoChangesAllowed,
            2 => self::FormFillAndSignatures,
            3 => self::FormFillSignaturesAndAnnotations,
            default => null,
        };
    }
}

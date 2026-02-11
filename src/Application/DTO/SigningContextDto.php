<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

use SignerPHP\Domain\ValueObject\VerifiedCertificate;

final readonly class SigningContextDto
{
    public function __construct(
        public SignPdfRequestDto $request,
        public VerifiedCertificate $verifiedCertificate,
    ) {}
}

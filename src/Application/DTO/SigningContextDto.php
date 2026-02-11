<?php

declare(strict_types=1);

namespace PdfSigner\Application\DTO;

use PdfSigner\Domain\ValueObject\VerifiedCertificate;

final readonly class SigningContextDto
{
    public function __construct(
        public SignPdfRequestDto $request,
        public VerifiedCertificate $verifiedCertificate,
    ) {}
}

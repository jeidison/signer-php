<?php

declare(strict_types=1);

namespace PdfSigner\Application\Service;

use PdfSigner\Application\Contract\CertificateValidatorInterface;
use PdfSigner\Application\Contract\PdfSigningEngineInterface;
use PdfSigner\Application\DTO\SigningContextDto;
use PdfSigner\Application\DTO\SignPdfRequestDto;

final readonly class PdfSigningService
{
    public function __construct(
        private CertificateValidatorInterface $certificateValidator,
        private PdfSigningEngineInterface $signingEngine,
    ) {}

    public function sign(SignPdfRequestDto $request): string
    {
        $verified = $this->certificateValidator->validate($request->certificate);
        $context = new SigningContextDto($request, $verified);

        return $this->signingEngine->sign($context);
    }
}

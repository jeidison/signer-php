<?php

declare(strict_types=1);

namespace SignerPHP\Application\Service;

use SignerPHP\Application\Contract\CertificateValidatorInterface;
use SignerPHP\Application\Contract\PdfSigningEngineInterface;
use SignerPHP\Application\DTO\SigningContextDto;
use SignerPHP\Application\DTO\SignPdfRequestDto;

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

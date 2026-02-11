<?php

declare(strict_types=1);

namespace PdfSigner\Application\Factory;

use PdfSigner\Application\DTO\CertificateCredentialsDto;
use PdfSigner\Application\DTO\PdfContentDto;
use PdfSigner\Application\DTO\SigningOptionsDto;
use PdfSigner\Application\DTO\SignPdfRequestDto;

final class SignPdfRequestFactory
{
    public function fromParts(
        PdfContentDto $pdf,
        CertificateCredentialsDto $certificate,
        ?SigningOptionsDto $options = null,
    ): SignPdfRequestDto {
        return SignPdfRequestDto::fromRequired(
            $pdf,
            $certificate,
            $options ?? SigningOptionsDto::empty(),
        );
    }
}

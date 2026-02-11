<?php

declare(strict_types=1);

namespace SignerPHP\Application\Factory;

use SignerPHP\Application\DTO\CertificateCredentialsDto;
use SignerPHP\Application\DTO\PdfContentDto;
use SignerPHP\Application\DTO\SigningOptionsDto;
use SignerPHP\Application\DTO\SignPdfRequestDto;

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

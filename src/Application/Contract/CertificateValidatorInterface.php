<?php

declare(strict_types=1);

namespace PdfSigner\Application\Contract;

use PdfSigner\Application\DTO\CertificateCredentialsDto;
use PdfSigner\Domain\ValueObject\VerifiedCertificate;

interface CertificateValidatorInterface
{
    public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate;
}

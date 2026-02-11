<?php

declare(strict_types=1);

namespace SignerPHP\Application\Contract;

use SignerPHP\Application\DTO\CertificateCredentialsDto;
use SignerPHP\Domain\ValueObject\VerifiedCertificate;

interface CertificateValidatorInterface
{
    public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate;
}

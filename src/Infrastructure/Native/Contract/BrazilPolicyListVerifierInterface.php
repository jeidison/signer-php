<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Contract;

use PdfSigner\Application\DTO\SignatureValidationOptionsDto;
use PdfSigner\Infrastructure\Native\ValueObject\SignaturePolicyVerification;

interface BrazilPolicyListVerifierInterface
{
    public function verifyPadesPolicy(SignatureValidationOptionsDto $options): SignaturePolicyVerification;
}

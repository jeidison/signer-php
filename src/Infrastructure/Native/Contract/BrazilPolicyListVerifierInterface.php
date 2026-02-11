<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Contract;

use SignerPHP\Application\DTO\SignatureValidationOptionsDto;
use SignerPHP\Infrastructure\Native\ValueObject\SignaturePolicyVerification;

interface BrazilPolicyListVerifierInterface
{
    public function verifyPadesPolicy(SignatureValidationOptionsDto $options): SignaturePolicyVerification;
}

<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Contract;

use SignerPHP\Application\DTO\SignatureValidationOptionsDto;
use SignerPHP\Infrastructure\Native\ValueObject\SignatureTrustVerification;

interface SignatureTrustVerifierInterface
{
    public function verify(string $signatureHex, SignatureValidationOptionsDto $options): SignatureTrustVerification;
}

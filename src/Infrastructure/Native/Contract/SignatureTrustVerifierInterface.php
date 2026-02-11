<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Contract;

use PdfSigner\Application\DTO\SignatureValidationOptionsDto;
use PdfSigner\Infrastructure\Native\ValueObject\SignatureTrustVerification;

interface SignatureTrustVerifierInterface
{
    public function verify(string $signatureHex, SignatureValidationOptionsDto $options): SignatureTrustVerification;
}

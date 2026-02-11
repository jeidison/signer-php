<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Contract;

use PdfSigner\Infrastructure\Native\ValueObject\SignatureCryptoVerification;

interface SignatureCryptoVerifierInterface
{
    public function verify(string $signedContent, string $signatureHex): SignatureCryptoVerification;
}

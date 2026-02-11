<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Contract;

use SignerPHP\Infrastructure\Native\ValueObject\SignatureCryptoVerification;

interface SignatureCryptoVerifierInterface
{
    public function verify(string $signedContent, string $signatureHex): SignatureCryptoVerification;
}

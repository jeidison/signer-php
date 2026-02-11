<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Contract;

use PdfSigner\Infrastructure\PdfCore\Buffer;
use PdfSigner\Infrastructure\PdfCore\Signature;

interface Pkcs7SignerInterface
{
    public function sign(Signature $signatureHandler, Buffer $signableDocument): string;
}

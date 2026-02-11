<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Contract;

use SignerPHP\Infrastructure\PdfCore\Buffer;
use SignerPHP\Infrastructure\PdfCore\Signature;

interface Pkcs7SignerInterface
{
    public function sign(Signature $signatureHandler, Buffer $signableDocument): string;
}

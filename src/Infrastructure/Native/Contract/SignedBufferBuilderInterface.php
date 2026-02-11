<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Contract;

use SignerPHP\Application\DTO\SigningContextDto;
use SignerPHP\Infrastructure\PdfCore\Buffer;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\Signature;

interface SignedBufferBuilderInterface
{
    public function build(PdfDocument $pdfDocument, Signature $signatureHandler, SigningContextDto $context): Buffer;
}

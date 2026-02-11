<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Contract;

use PdfSigner\Application\DTO\SigningContextDto;
use PdfSigner\Infrastructure\PdfCore\Buffer;
use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\Signature;

interface SignedBufferBuilderInterface
{
    public function build(PdfDocument $pdfDocument, Signature $signatureHandler, SigningContextDto $context): Buffer;
}

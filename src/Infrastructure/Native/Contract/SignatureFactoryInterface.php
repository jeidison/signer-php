<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Contract;

use PdfSigner\Application\DTO\SigningContextDto;
use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\Signature;

interface SignatureFactoryInterface
{
    public function create(SigningContextDto $context, PdfDocument $pdfDocument): Signature;
}

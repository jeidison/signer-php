<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Contract;

use SignerPHP\Application\DTO\SigningContextDto;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\Signature;

interface SignatureFactoryInterface
{
    public function create(SigningContextDto $context, PdfDocument $pdfDocument): Signature;
}

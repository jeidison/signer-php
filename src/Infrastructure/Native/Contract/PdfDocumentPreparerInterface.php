<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Contract;

use PdfSigner\Infrastructure\PdfCore\PdfDocument;

interface PdfDocumentPreparerInterface
{
    public function prepare(string $pdfContent): PdfDocument;
}

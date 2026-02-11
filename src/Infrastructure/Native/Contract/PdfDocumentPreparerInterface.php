<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Contract;

use SignerPHP\Infrastructure\PdfCore\PdfDocument;

interface PdfDocumentPreparerInterface
{
    public function prepare(string $pdfContent): PdfDocument;
}

<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Contract;

use PdfSigner\Infrastructure\Native\ValueObject\ExtractedPdfSignature;

interface PdfSignatureExtractorInterface
{
    /**
     * @return array<int, ExtractedPdfSignature>
     */
    public function extract(string $pdfContent): array;
}

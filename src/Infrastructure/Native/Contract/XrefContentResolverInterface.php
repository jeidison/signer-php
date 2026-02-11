<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Contract;

use PdfSigner\Infrastructure\PdfCore\Buffer;
use PdfSigner\Infrastructure\PdfCore\PdfDocument;

interface XrefContentResolverInterface
{
    /**
     * @param  array<int, int>  $objectOffsets
     */
    public function resolve(PdfDocument $pdfDocument, array $objectOffsets, int $xrefOffset): Buffer;
}

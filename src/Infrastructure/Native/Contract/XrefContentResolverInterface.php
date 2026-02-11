<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Contract;

use SignerPHP\Infrastructure\PdfCore\Buffer;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;

interface XrefContentResolverInterface
{
    /**
     * @param  array<int, int>  $objectOffsets
     */
    public function resolve(PdfDocument $pdfDocument, array $objectOffsets, int $xrefOffset): Buffer;
}

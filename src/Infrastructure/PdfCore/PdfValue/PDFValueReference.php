<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\PdfValue;

class PDFValueReference extends PDFValueSimple
{
    public function __construct($oid)
    {
        parent::__construct(sprintf('%d 0 R', $oid));
    }
}

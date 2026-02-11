<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\PdfValue;

class PDFValueType extends PDFValue
{
    public function __toString(): string
    {
        return '/'.trim((string) $this->value);
    }
}

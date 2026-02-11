<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore\PdfValue;

class PDFValueSimple extends PDFValue
{
    public function push($value): bool
    {
        if ($value::class === static::class) {
            $this->value = $this->value.' '.$value->val();

            return true;
        }

        return false;
    }

    public function asObjectReferenceOrNull(): int|array|null
    {
        if (! preg_match('/^\s*([0-9]+)\s+([0-9]+)\s+R\s*$/ms', (string) $this->value, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    public function asIntOrNull(): ?int
    {
        if (! is_numeric($this->value)) {
            return null;
        }

        return (int) $this->value;
    }
}

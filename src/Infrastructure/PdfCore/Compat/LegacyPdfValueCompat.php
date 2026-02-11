<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Compat;

trait LegacyPdfValueCompat
{
    /**
     * @deprecated Use asIntOrNull() instead.
     */
    public function getInt(): int|false
    {
        return $this->asIntOrNull() ?? false;
    }

    /**
     * @deprecated Use asObjectReferenceOrNull() instead.
     */
    public function getObjectReferenced(): int|array|false
    {
        return $this->asObjectReferenceOrNull() ?? false;
    }
}

<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Contract;

use SignerPHP\Application\DTO\ProtectionOptionsDto;

interface PdfProtectionApplierInterface
{
    public function apply(string $pdfContent, ProtectionOptionsDto $options): string;
}

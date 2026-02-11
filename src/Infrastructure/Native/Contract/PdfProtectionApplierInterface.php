<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Contract;

use PdfSigner\Application\DTO\ProtectionOptionsDto;

interface PdfProtectionApplierInterface
{
    public function apply(string $pdfContent, ProtectionOptionsDto $options): string;
}

<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Contract;

use PdfSigner\Application\DTO\TimestampOptionsDto;

interface DocumentTimestampApplierInterface
{
    public function apply(string $signedPdfContent, TimestampOptionsDto $options): string;
}

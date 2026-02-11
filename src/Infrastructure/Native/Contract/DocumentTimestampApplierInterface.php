<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Contract;

use SignerPHP\Application\DTO\TimestampOptionsDto;

interface DocumentTimestampApplierInterface
{
    public function apply(string $signedPdfContent, TimestampOptionsDto $options): string;
}

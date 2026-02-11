<?php

declare(strict_types=1);

namespace PdfSigner\Application\Contract;

use PdfSigner\Application\DTO\SigningContextDto;

interface PdfSigningEngineInterface
{
    public function sign(SigningContextDto $context): string;
}

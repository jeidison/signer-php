<?php

declare(strict_types=1);

namespace SignerPHP\Application\Contract;

use SignerPHP\Application\DTO\SigningContextDto;

interface PdfSigningEngineInterface
{
    public function sign(SigningContextDto $context): string;
}

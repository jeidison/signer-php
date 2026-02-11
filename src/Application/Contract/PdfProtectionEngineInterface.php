<?php

declare(strict_types=1);

namespace SignerPHP\Application\Contract;

use SignerPHP\Application\DTO\ProtectPdfRequestDto;

interface PdfProtectionEngineInterface
{
    public function protect(ProtectPdfRequestDto $request): string;
}

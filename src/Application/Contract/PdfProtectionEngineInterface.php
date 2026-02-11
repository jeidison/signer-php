<?php

declare(strict_types=1);

namespace PdfSigner\Application\Contract;

use PdfSigner\Application\DTO\ProtectPdfRequestDto;

interface PdfProtectionEngineInterface
{
    public function protect(ProtectPdfRequestDto $request): string;
}

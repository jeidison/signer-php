<?php

declare(strict_types=1);

namespace SignerPHP\Application\Service;

use SignerPHP\Application\Contract\PdfProtectionEngineInterface;
use SignerPHP\Application\DTO\ProtectPdfRequestDto;

final readonly class PdfProtectionService
{
    public function __construct(private PdfProtectionEngineInterface $protectionEngine) {}

    public function protect(ProtectPdfRequestDto $request): string
    {
        return $this->protectionEngine->protect($request);
    }
}

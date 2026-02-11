<?php

declare(strict_types=1);

namespace PdfSigner\Application\Service;

use PdfSigner\Application\Contract\PdfProtectionEngineInterface;
use PdfSigner\Application\DTO\ProtectPdfRequestDto;

final readonly class PdfProtectionService
{
    public function __construct(private PdfProtectionEngineInterface $protectionEngine) {}

    public function protect(ProtectPdfRequestDto $request): string
    {
        return $this->protectionEngine->protect($request);
    }
}

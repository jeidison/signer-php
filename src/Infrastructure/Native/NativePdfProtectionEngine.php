<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native;

use PdfSigner\Application\Contract\PdfProtectionEngineInterface;
use PdfSigner\Application\DTO\ProtectPdfRequestDto;
use PdfSigner\Domain\Exception\ProtectionProcessException;
use PdfSigner\Infrastructure\Native\Contract\PdfProtectionApplierInterface;
use PdfSigner\Infrastructure\Native\Service\QpdfPdfProtectionApplier;

final readonly class NativePdfProtectionEngine implements PdfProtectionEngineInterface
{
    public function __construct(
        private PdfProtectionApplierInterface $protectionApplier = new QpdfPdfProtectionApplier,
    ) {}

    public function protect(ProtectPdfRequestDto $request): string
    {
        try {
            return $this->protectionApplier->apply($request->pdf->content, $request->options);
        } catch (\Throwable $throwable) {
            throw new ProtectionProcessException(
                sprintf('Could not apply PDF protection using native v1 engine. Root cause: %s', $throwable->getMessage()),
                previous: $throwable,
            );
        }
    }
}

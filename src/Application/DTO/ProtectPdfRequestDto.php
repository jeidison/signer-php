<?php

declare(strict_types=1);

namespace PdfSigner\Application\DTO;

final readonly class ProtectPdfRequestDto
{
    public function __construct(
        public PdfContentDto $pdf,
        public ProtectionOptionsDto $options,
    ) {}
}

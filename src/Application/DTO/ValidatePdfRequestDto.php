<?php

declare(strict_types=1);

namespace PdfSigner\Application\DTO;

final readonly class ValidatePdfRequestDto
{
    public function __construct(
        public PdfContentDto $pdf,
        public SignatureValidationOptionsDto $options = new SignatureValidationOptionsDto,
    ) {}
}

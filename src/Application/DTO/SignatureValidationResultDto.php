<?php

declare(strict_types=1);

namespace PdfSigner\Application\DTO;

final readonly class SignatureValidationResultDto
{
    /**
     * @param  array<int, SignatureValidationEntryDto>  $entries
     */
    public function __construct(
        public bool $hasSignatures,
        public bool $allValid,
        public array $entries,
    ) {}
}

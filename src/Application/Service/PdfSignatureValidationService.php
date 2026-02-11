<?php

declare(strict_types=1);

namespace PdfSigner\Application\Service;

use PdfSigner\Application\Contract\PdfSignatureValidationEngineInterface;
use PdfSigner\Application\DTO\SignatureValidationResultDto;
use PdfSigner\Application\DTO\ValidatePdfRequestDto;

final readonly class PdfSignatureValidationService
{
    public function __construct(private PdfSignatureValidationEngineInterface $validationEngine) {}

    public function validate(ValidatePdfRequestDto $request): SignatureValidationResultDto
    {
        return $this->validationEngine->validate($request);
    }
}

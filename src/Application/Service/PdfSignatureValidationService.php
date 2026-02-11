<?php

declare(strict_types=1);

namespace SignerPHP\Application\Service;

use SignerPHP\Application\Contract\PdfSignatureValidationEngineInterface;
use SignerPHP\Application\DTO\SignatureValidationResultDto;
use SignerPHP\Application\DTO\ValidatePdfRequestDto;

final readonly class PdfSignatureValidationService
{
    public function __construct(private PdfSignatureValidationEngineInterface $validationEngine) {}

    public function validate(ValidatePdfRequestDto $request): SignatureValidationResultDto
    {
        return $this->validationEngine->validate($request);
    }
}

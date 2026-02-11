<?php

declare(strict_types=1);

namespace PdfSigner\Application\Contract;

use PdfSigner\Application\DTO\SignatureValidationResultDto;
use PdfSigner\Application\DTO\ValidatePdfRequestDto;

interface PdfSignatureValidationEngineInterface
{
    public function validate(ValidatePdfRequestDto $request): SignatureValidationResultDto;
}

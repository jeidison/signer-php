<?php

declare(strict_types=1);

namespace SignerPHP\Application\Contract;

use SignerPHP\Application\DTO\SignatureValidationResultDto;
use SignerPHP\Application\DTO\ValidatePdfRequestDto;

interface PdfSignatureValidationEngineInterface
{
    public function validate(ValidatePdfRequestDto $request): SignatureValidationResultDto;
}

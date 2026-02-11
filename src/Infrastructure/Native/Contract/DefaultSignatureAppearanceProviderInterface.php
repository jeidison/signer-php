<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Contract;

use PdfSigner\Application\DTO\SignatureAppearanceDto;

interface DefaultSignatureAppearanceProviderInterface
{
    public function makeDefault(): SignatureAppearanceDto;
}

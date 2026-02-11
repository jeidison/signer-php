<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Contract;

use SignerPHP\Application\DTO\SignatureAppearanceDto;

interface DefaultSignatureAppearanceProviderInterface
{
    public function makeDefault(): SignatureAppearanceDto;
}

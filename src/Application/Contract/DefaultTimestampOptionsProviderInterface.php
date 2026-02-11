<?php

declare(strict_types=1);

namespace PdfSigner\Application\Contract;

use PdfSigner\Application\DTO\TimestampOptionsDto;

interface DefaultTimestampOptionsProviderInterface
{
    public function makeDefault(): TimestampOptionsDto;
}

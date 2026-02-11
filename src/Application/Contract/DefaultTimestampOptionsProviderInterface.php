<?php

declare(strict_types=1);

namespace SignerPHP\Application\Contract;

use SignerPHP\Application\DTO\TimestampOptionsDto;

interface DefaultTimestampOptionsProviderInterface
{
    public function makeDefault(): TimestampOptionsDto;
}

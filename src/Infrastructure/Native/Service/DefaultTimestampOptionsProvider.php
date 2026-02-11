<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Service;

use SignerPHP\Application\Contract\DefaultTimestampOptionsProviderInterface;
use SignerPHP\Application\DTO\TimestampOptionsDto;

final class DefaultTimestampOptionsProvider implements DefaultTimestampOptionsProviderInterface
{
    public function makeDefault(): TimestampOptionsDto
    {
        return new TimestampOptionsDto(
            tsaUrl: 'https://freetsa.org/tsr',
            hashAlgorithm: 'sha256',
            certReq: true,
            username: null,
            password: null,
            timeoutSeconds: 15,
        );
    }
}

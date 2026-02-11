<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Contract;

use SignerPHP\Infrastructure\Native\ValueObject\HttpResponse;

interface HttpClientInterface
{
    /**
     * @param  array<int, string>  $headers
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        string $body = '',
        int $timeoutSeconds = 10,
        bool $followRedirects = false,
    ): HttpResponse;
}

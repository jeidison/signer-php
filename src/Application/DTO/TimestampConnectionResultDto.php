<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final readonly class TimestampConnectionResultDto
{
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public ?int $statusCode = null,
    ) {}
}


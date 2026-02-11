<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final readonly class SignatureActorDto
{
    public function __construct(
        public ?string $name = null,
        public ?string $contactInfo = null,
    ) {}
}

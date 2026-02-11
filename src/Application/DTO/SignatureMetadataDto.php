<?php

declare(strict_types=1);

namespace PdfSigner\Application\DTO;

final readonly class SignatureMetadataDto
{
    public function __construct(
        public ?string $reason = null,
        public ?string $location = null,
        public ?SignatureActorDto $actor = null,
    ) {}
}

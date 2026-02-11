<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\ValueObject;

final readonly class SignatureTrustVerification
{
    public function __construct(
        public bool $valid,
        public ?string $message = null,
    ) {}
}

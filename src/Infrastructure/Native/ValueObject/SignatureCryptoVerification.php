<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\ValueObject;

final readonly class SignatureCryptoVerification
{
    public function __construct(
        public bool $valid,
        public ?string $message = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\ValueObject;

final readonly class HttpResponse
{
    public function __construct(
        public int $statusCode,
        public string $body,
        public ?string $transportError = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}

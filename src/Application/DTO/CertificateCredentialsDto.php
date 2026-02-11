<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

use InvalidArgumentException;

final readonly class CertificateCredentialsDto
{
    public function __construct(
        public ?string $certificatePath,
        public string $password,
        public ?string $certificateContent = null,
    ) {
        $hasPath = is_string($this->certificatePath) && $this->certificatePath !== '';
        $hasContent = is_string($this->certificateContent) && $this->certificateContent !== '';

        if (! $hasPath && ! $hasContent) {
            throw new InvalidArgumentException('Certificate path or content is required.');
        }
    }

    public static function fromPath(string $path, string $password): self
    {
        return new self($path, $password, null);
    }

    public static function fromContent(string $content, string $password): self
    {
        return new self(null, $password, $content);
    }
}

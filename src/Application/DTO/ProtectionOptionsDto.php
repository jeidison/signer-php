<?php

declare(strict_types=1);

namespace PdfSigner\Application\DTO;

use InvalidArgumentException;

final readonly class ProtectionOptionsDto
{
    public function __construct(
        public ?string $ownerPassword = null,
        public string $userPassword = '',
        public bool $allowPrint = true,
        public bool $allowCopy = true,
        public bool $allowModify = true,
        public int $keyLengthBits = 256,
        public bool $encryptMetadata = true,
    ) {
        if ($ownerPassword === '') {
            throw new InvalidArgumentException('Owner password cannot be an empty string.');
        }

        if (! in_array($this->keyLengthBits, [128, 256], true)) {
            throw new InvalidArgumentException('Only 128-bit and 256-bit PDF protection are supported.');
        }
    }

    public static function preventCopy(
        ?string $ownerPassword = null,
        string $userPassword = '',
        bool $allowPrint = true,
        bool $allowModify = true,
    ): self {
        return new self(
            ownerPassword: $ownerPassword,
            userPassword: $userPassword,
            allowPrint: $allowPrint,
            allowCopy: false,
            allowModify: $allowModify,
        );
    }
}

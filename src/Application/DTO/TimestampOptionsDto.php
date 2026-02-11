<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final readonly class TimestampOptionsDto
{
    public HashAlgorithm $hashAlgorithm;

    public function __construct(
        public string $tsaUrl,
        HashAlgorithm|string $hashAlgorithm = HashAlgorithm::Sha256,
        public bool $certReq = true,
        public ?string $username = null,
        public ?string $password = null,
        public int $timeoutSeconds = 15,
        public ?string $oauthClientId = null,
        public ?string $oauthClientSecret = null,
        public ?string $oauthTokenUrl = null,
    ) {
        $this->hashAlgorithm = HashAlgorithm::fromString($hashAlgorithm);
    }
}

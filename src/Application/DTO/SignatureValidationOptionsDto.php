<?php

declare(strict_types=1);

namespace PdfSigner\Application\DTO;

final readonly class SignatureValidationOptionsDto
{
    public function __construct(
        public bool $checkTrustChain = false,
        public ?string $trustStorePath = null,
        public ?string $trustAnchorsDirectory = null,
        public ?array $trustAnchorsUrls = null,
        public ?string $policy = null,
        public bool $checkPolicyList = false,
        public ?string $lpaUrlAsn1Pades = null,
        public ?string $lpaUrlAsn1SignaturePades = null,
    ) {}
}

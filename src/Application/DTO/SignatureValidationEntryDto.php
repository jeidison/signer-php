<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final readonly class SignatureValidationEntryDto
{
    /**
     * @param  array{0:int,1:int,2:int,3:int}  $byteRange
     */
    public function __construct(
        public int $index,
        public array $byteRange,
        public bool $byteRangeValid,
        public bool $cryptoValid,
        public ?bool $trustValid,
        public ?bool $policyValid,
        public bool $valid,
        public ?string $reason = null,
    ) {}
}

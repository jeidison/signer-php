<?php

declare(strict_types=1);

namespace PdfSigner\Domain\ValueObject;

use PdfSigner\Application\DTO\CertificateCredentialsDto;

final readonly class VerifiedCertificate
{
    /**
     * @param  array<string, mixed>  $parsed
     * @param  array{cert: string, pkey: string, extracerts?: mixed}  $bundle
     */
    public function __construct(
        public CertificateCredentialsDto $credentials,
        public array $parsed,
        public array $bundle,
    ) {}

    public function isExpiredAt(int $timestamp): bool
    {
        $validTo = (int) ($this->parsed['validTo_time_t'] ?? 0);

        return $validTo < $timestamp;
    }
}

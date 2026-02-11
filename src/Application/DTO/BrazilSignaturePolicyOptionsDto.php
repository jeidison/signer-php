<?php

declare(strict_types=1);

namespace PdfSigner\Application\DTO;

final readonly class BrazilSignaturePolicyOptionsDto
{
    public const SERPRO_TOKEN_URL = 'https://gateway.apiserpro.serpro.gov.br/token';

    public const SERPRO_STAMP_URL = 'https://gateway.apiserpro.serpro.gov.br/apitimestamp/v1/stamps-asn1';

    public HashAlgorithm $hashAlgorithm;

    public function __construct(
        public string $tsaUrl = self::SERPRO_STAMP_URL,
        HashAlgorithm|string $hashAlgorithm = HashAlgorithm::Sha256,
        public int $timeoutSeconds = 20,
        public bool $certReq = true,
        public ?string $serproConsumerKey = null,
        public ?string $serproConsumerSecret = null,
        public string $serproTokenUrl = self::SERPRO_TOKEN_URL,
    ) {
        $this->hashAlgorithm = HashAlgorithm::fromString($hashAlgorithm);
    }

    public static function tsa(
        string $tsaUrl,
        HashAlgorithm|string $hashAlgorithm = HashAlgorithm::Sha256,
        int $timeoutSeconds = 20,
        bool $certReq = true,
    ): self {
        return new self(
            tsaUrl: $tsaUrl,
            hashAlgorithm: $hashAlgorithm,
            timeoutSeconds: $timeoutSeconds,
            certReq: $certReq,
        );
    }

    public static function serpro(
        string $consumerKey,
        string $consumerSecret,
        HashAlgorithm|string $hashAlgorithm = HashAlgorithm::Sha256,
        int $timeoutSeconds = 20,
    ): self {
        return new self(
            tsaUrl: self::SERPRO_STAMP_URL,
            hashAlgorithm: $hashAlgorithm,
            timeoutSeconds: $timeoutSeconds,
            certReq: true,
            serproConsumerKey: $consumerKey,
            serproConsumerSecret: $consumerSecret,
            serproTokenUrl: self::SERPRO_TOKEN_URL,
        );
    }

    public function withTsa(string $tsaUrl): self
    {
        return new self(
            tsaUrl: $tsaUrl,
            hashAlgorithm: $this->hashAlgorithm->value,
            timeoutSeconds: $this->timeoutSeconds,
            certReq: $this->certReq,
            serproConsumerKey: $this->serproConsumerKey,
            serproConsumerSecret: $this->serproConsumerSecret,
            serproTokenUrl: $this->serproTokenUrl,
        );
    }

    public function withHashAlgorithm(HashAlgorithm|string $hashAlgorithm): self
    {
        return new self(
            tsaUrl: $this->tsaUrl,
            hashAlgorithm: $hashAlgorithm,
            timeoutSeconds: $this->timeoutSeconds,
            certReq: $this->certReq,
            serproConsumerKey: $this->serproConsumerKey,
            serproConsumerSecret: $this->serproConsumerSecret,
            serproTokenUrl: $this->serproTokenUrl,
        );
    }

    public function withTimeoutSeconds(int $timeoutSeconds): self
    {
        return new self(
            tsaUrl: $this->tsaUrl,
            hashAlgorithm: $this->hashAlgorithm->value,
            timeoutSeconds: $timeoutSeconds,
            certReq: $this->certReq,
            serproConsumerKey: $this->serproConsumerKey,
            serproConsumerSecret: $this->serproConsumerSecret,
            serproTokenUrl: $this->serproTokenUrl,
        );
    }

    public function withOAuth(string $clientId, string $clientSecret, string $tokenUrl): self
    {
        return new self(
            tsaUrl: $this->tsaUrl,
            hashAlgorithm: $this->hashAlgorithm->value,
            timeoutSeconds: $this->timeoutSeconds,
            certReq: $this->certReq,
            serproConsumerKey: $clientId,
            serproConsumerSecret: $clientSecret,
            serproTokenUrl: $tokenUrl,
        );
    }

    public function toTimestampOptions(): TimestampOptionsDto
    {
        return new TimestampOptionsDto(
            tsaUrl: $this->tsaUrl,
            hashAlgorithm: $this->hashAlgorithm->value,
            certReq: $this->certReq,
            timeoutSeconds: $this->timeoutSeconds,
            oauthClientId: $this->serproConsumerKey,
            oauthClientSecret: $this->serproConsumerSecret,
            oauthTokenUrl: $this->serproConsumerTokenUrl(),
        );
    }

    private function serproConsumerTokenUrl(): ?string
    {
        if ($this->serproConsumerKey === null || $this->serproConsumerSecret === null) {
            return null;
        }

        return $this->serproTokenUrl;
    }
}

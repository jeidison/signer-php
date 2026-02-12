<?php

declare(strict_types=1);

namespace SignerPHP\Application\Service;

use SignerPHP\Application\DTO\TimestampConnectionResultDto;
use SignerPHP\Application\DTO\TimestampOptionsDto;
use SignerPHP\Domain\Exception\SignProcessException;
use SignerPHP\Infrastructure\Native\Contract\TimestampTokenProviderInterface;
use SignerPHP\Infrastructure\Native\Service\OpenSslRfc3161TimestampTokenProvider;

final readonly class TimestampService
{
    public function __construct(
        private TimestampTokenProviderInterface $tokenProvider = new OpenSslRfc3161TimestampTokenProvider,
    ) {}

    public function requestTokenHex(string $content, TimestampOptionsDto $options): string
    {
        $byteLength = strlen($content);

        return $this->tokenProvider->requestTokenHex($content, [0, $byteLength, 0, 0], $options);
    }

    public function requestTokenBase64(string $content, TimestampOptionsDto $options): string
    {
        $hex = $this->requestTokenHex($content, $options);
        $binary = hex2bin($hex);
        if (! is_string($binary) || $binary === '') {
            throw new SignProcessException('Could not convert timestamp token hex to binary.');
        }

        return base64_encode($binary);
    }

    public function testConnection(TimestampOptionsDto $options, ?string $probeContent = null): TimestampConnectionResultDto
    {
        $probe = $probeContent ?? 'signer-php-timestamp-probe:'.gmdate('c');

        try {
            $tokenHex = $this->requestTokenHex($probe, $options);

            return new TimestampConnectionResultDto(
                success: true,
                message: sprintf('Timestamp token generated successfully (%d hex chars).', strlen($tokenHex))
            );
        } catch (\Throwable $exception) {
            $statusCode = $this->extractHttpStatusCode($exception->getMessage());

            return new TimestampConnectionResultDto(
                success: false,
                message: $exception->getMessage(),
                statusCode: $statusCode,
            );
        }
    }

    private function extractHttpStatusCode(string $message): ?int
    {
        if (preg_match('/Status:\s*(\d{3})/i', $message, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }
}


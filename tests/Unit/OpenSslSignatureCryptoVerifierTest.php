<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\Native\Contract\ProcessRunnerInterface;
use PdfSigner\Infrastructure\Native\Service\OpenSslSignatureCryptoVerifier;
use PdfSigner\Infrastructure\Native\ValueObject\ProcessResult;
use PHPUnit\Framework\TestCase;

final class OpenSslSignatureCryptoVerifierTest extends TestCase
{
    public function test_verify_returns_invalid_for_empty_signature_hex(): void
    {
        $verifier = new OpenSslSignatureCryptoVerifier;
        $result = $verifier->verify('content', '');

        self::assertFalse($result->valid);
    }

    public function test_verify_returns_invalid_for_zero_placeholder_hex(): void
    {
        $verifier = new OpenSslSignatureCryptoVerifier;
        $result = $verifier->verify('content', '0000000000');

        self::assertFalse($result->valid);
        self::assertSame('Empty signature contents.', $result->message);
    }

    public function test_verify_returns_invalid_for_non_hex_signature(): void
    {
        $verifier = new OpenSslSignatureCryptoVerifier;

        set_error_handler(static function (): bool {
            return true;
        });
        try {
            $result = $verifier->verify('content', 'GG');
        } finally {
            restore_error_handler();
        }

        self::assertFalse($result->valid);
        self::assertSame('Invalid signature hex payload.', $result->message);
    }

    public function test_verify_returns_invalid_when_openssl_verify_fails(): void
    {
        $runner = new class implements ProcessRunnerInterface
        {
            public function run(string $command): ProcessResult
            {
                return new ProcessResult(1, ['cms verify failure']);
            }
        };

        $verifier = new OpenSslSignatureCryptoVerifier($runner);
        $result = $verifier->verify('content', 'AA');

        self::assertFalse($result->valid);
        self::assertSame('cms verify failure', $result->message);
    }

    public function test_verify_returns_valid_when_openssl_verify_succeeds(): void
    {
        $runner = new class implements ProcessRunnerInterface
        {
            public string $command = '';

            public function run(string $command): ProcessResult
            {
                $this->command = $command;

                return new ProcessResult(0, ['ok']);
            }
        };

        $verifier = new OpenSslSignatureCryptoVerifier($runner);
        $result = $verifier->verify('content', 'AA');

        self::assertTrue($result->valid);
        self::assertStringContainsString('openssl cms -verify', $runner->command);
    }
}

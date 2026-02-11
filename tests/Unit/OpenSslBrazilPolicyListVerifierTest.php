<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Application\DTO\SignatureValidationOptionsDto;
use SignerPHP\Infrastructure\Native\Contract\HttpClientInterface;
use SignerPHP\Infrastructure\Native\Contract\ProcessRunnerInterface;
use SignerPHP\Infrastructure\Native\Service\OpenSslBrazilPolicyListVerifier;
use SignerPHP\Infrastructure\Native\ValueObject\HttpResponse;
use SignerPHP\Infrastructure\Native\ValueObject\ProcessResult;

final class OpenSslBrazilPolicyListVerifierTest extends TestCase
{
    public function test_verify_returns_invalid_when_lpa_urls_are_missing(): void
    {
        $verifier = new OpenSslBrazilPolicyListVerifier;
        $result = $verifier->verifyPadesPolicy(new SignatureValidationOptionsDto);

        self::assertFalse($result->valid);
        self::assertStringContainsString('LPA PAdES URLs are not configured', (string) $result->message);
    }

    public function test_verify_returns_invalid_when_download_fails(): void
    {
        $httpClient = new class implements HttpClientInterface
        {
            public function request(
                string $method,
                string $url,
                array $headers = [],
                string $body = '',
                int $timeoutSeconds = 10,
                bool $followRedirects = false
            ): HttpResponse {
                return new HttpResponse(500, '');
            }
        };

        $verifier = new OpenSslBrazilPolicyListVerifier($httpClient);
        $result = $verifier->verifyPadesPolicy(new SignatureValidationOptionsDto(
            lpaUrlAsn1Pades: 'https://example.local/lpa.der',
            lpaUrlAsn1SignaturePades: 'https://example.local/lpa.p7s',
        ));

        self::assertFalse($result->valid);
        self::assertStringContainsString('Could not download LPA PAdES ASN.1', (string) $result->message);
    }

    public function test_verify_returns_invalid_when_signature_download_fails(): void
    {
        $httpClient = new class implements HttpClientInterface
        {
            private int $calls = 0;

            public function request(
                string $method,
                string $url,
                array $headers = [],
                string $body = '',
                int $timeoutSeconds = 10,
                bool $followRedirects = false
            ): HttpResponse {
                $this->calls++;

                if ($this->calls === 1) {
                    return new HttpResponse(200, 'lpa');
                }

                return new HttpResponse(500, '');
            }
        };

        $verifier = new OpenSslBrazilPolicyListVerifier($httpClient);
        $result = $verifier->verifyPadesPolicy(new SignatureValidationOptionsDto(
            lpaUrlAsn1Pades: 'https://example.local/lpa.der',
            lpaUrlAsn1SignaturePades: 'https://example.local/lpa.p7s',
        ));

        self::assertFalse($result->valid);
        self::assertStringContainsString('Could not download LPA PAdES signature', (string) $result->message);
    }

    public function test_verify_returns_valid_when_der_command_succeeds(): void
    {
        $httpClient = $this->successHttpClient();
        $processRunner = new class implements ProcessRunnerInterface
        {
            public array $commands = [];

            public function run(string $command): ProcessResult
            {
                $this->commands[] = $command;

                return new ProcessResult(0);
            }
        };

        $verifier = new OpenSslBrazilPolicyListVerifier($httpClient, $processRunner);
        $result = $verifier->verifyPadesPolicy(new SignatureValidationOptionsDto(
            lpaUrlAsn1Pades: 'https://example.local/lpa.der',
            lpaUrlAsn1SignaturePades: 'https://example.local/lpa.p7s',
        ));

        self::assertTrue($result->valid);
        self::assertCount(1, $processRunner->commands);
        self::assertStringContainsString('-inform DER', $processRunner->commands[0]);
    }

    public function test_verify_fallbacks_to_smime_command_when_der_fails(): void
    {
        $httpClient = $this->successHttpClient();
        $processRunner = new class implements ProcessRunnerInterface
        {
            public int $calls = 0;

            public function run(string $command): ProcessResult
            {
                $this->calls++;
                if (str_contains($command, '-inform DER')) {
                    return new ProcessResult(1, ['der failed']);
                }

                return new ProcessResult(0);
            }
        };

        $verifier = new OpenSslBrazilPolicyListVerifier($httpClient, $processRunner);
        $result = $verifier->verifyPadesPolicy(new SignatureValidationOptionsDto(
            lpaUrlAsn1Pades: 'https://example.local/lpa.der',
            lpaUrlAsn1SignaturePades: 'https://example.local/lpa.p7s',
        ));

        self::assertTrue($result->valid);
        self::assertSame(2, $processRunner->calls);
    }

    public function test_verify_returns_invalid_when_both_commands_fail(): void
    {
        $httpClient = $this->successHttpClient();
        $processRunner = new class implements ProcessRunnerInterface
        {
            public function run(string $command): ProcessResult
            {
                return new ProcessResult(1, ['failed']);
            }
        };

        $verifier = new OpenSslBrazilPolicyListVerifier($httpClient, $processRunner);
        $result = $verifier->verifyPadesPolicy(new SignatureValidationOptionsDto(
            lpaUrlAsn1Pades: 'https://example.local/lpa.der',
            lpaUrlAsn1SignaturePades: 'https://example.local/lpa.p7s',
        ));

        self::assertFalse($result->valid);
        self::assertSame('LPA PAdES signature verification failed.', $result->message);
    }

    private function successHttpClient(): HttpClientInterface
    {
        return new class implements HttpClientInterface
        {
            public function request(
                string $method,
                string $url,
                array $headers = [],
                string $body = '',
                int $timeoutSeconds = 10,
                bool $followRedirects = false
            ): HttpResponse {
                return new HttpResponse(200, 'dummy-content');
            }
        };
    }
}

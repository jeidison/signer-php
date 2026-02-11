<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use SignerPHP\Application\DTO\SignatureValidationOptionsDto;
use SignerPHP\Infrastructure\Native\Contract\HttpClientInterface;
use SignerPHP\Infrastructure\Native\Contract\ProcessRunnerInterface;
use SignerPHP\Infrastructure\Native\Contract\SignatureCertificateCollectorInterface;
use SignerPHP\Infrastructure\Native\Service\IcpBrasilTrustAnchorBundleProvider;
use SignerPHP\Infrastructure\Native\Service\NativeFunctionOverrideState;
use SignerPHP\Infrastructure\Native\Service\OpenSslSignatureTrustVerifier;
use SignerPHP\Infrastructure\Native\ValueObject\HttpResponse;
use SignerPHP\Infrastructure\Native\ValueObject\ProcessResult;
use PHPUnit\Framework\TestCase;

final class OpenSslSignatureTrustVerifierTest extends TestCase
{
    protected function tearDown(): void
    {
        NativeFunctionOverrideState::$forceTempnamFailure = false;
        NativeFunctionOverrideState::$forceIsFileFalse = false;
    }

    public function test_verify_returns_valid_when_trust_validation_is_disabled(): void
    {
        $collector = new class implements SignatureCertificateCollectorInterface
        {
            public function collectDerCertificates(string $signatureHex): array
            {
                return [];
            }
        };

        $verifier = new OpenSslSignatureTrustVerifier($collector);
        $result = $verifier->verify('ABCD', new SignatureValidationOptionsDto(checkTrustChain: false));

        self::assertTrue($result->valid);
    }

    public function test_verify_returns_invalid_when_explicit_trust_store_does_not_exist(): void
    {
        $collector = new class implements SignatureCertificateCollectorInterface
        {
            public function collectDerCertificates(string $signatureHex): array
            {
                return [];
            }
        };

        $verifier = new OpenSslSignatureTrustVerifier($collector);
        $result = $verifier->verify('ABCD', new SignatureValidationOptionsDto(
            checkTrustChain: true,
            trustStorePath: '/path/that/does/not/exist.pem',
        ));

        self::assertFalse($result->valid);
        self::assertStringContainsString('No trust store found', (string) $result->message);
    }

    public function test_verify_returns_invalid_when_no_certificates_are_extracted(): void
    {
        $collector = new class implements SignatureCertificateCollectorInterface
        {
            public function collectDerCertificates(string $signatureHex): array
            {
                return [];
            }
        };

        $trustStore = tempnam(sys_get_temp_dir(), 'trust-store');
        self::assertNotFalse($trustStore);
        file_put_contents($trustStore, "-----BEGIN CERTIFICATE-----\n-----END CERTIFICATE-----\n");

        try {
            $verifier = new OpenSslSignatureTrustVerifier($collector);
            $result = $verifier->verify('ABCD', new SignatureValidationOptionsDto(
                checkTrustChain: true,
                trustStorePath: $trustStore,
            ));

            self::assertFalse($result->valid);
            self::assertStringContainsString('Could not extract certificates', (string) $result->message);
        } finally {
            @unlink($trustStore);
        }
    }

    public function test_verify_returns_invalid_when_signer_certificate_cannot_be_decoded(): void
    {
        $collector = new class implements SignatureCertificateCollectorInterface
        {
            public function collectDerCertificates(string $signatureHex): array
            {
                return [''];
            }
        };

        $trustStore = tempnam(sys_get_temp_dir(), 'trust-store');
        self::assertNotFalse($trustStore);
        file_put_contents($trustStore, "-----BEGIN CERTIFICATE-----\n-----END CERTIFICATE-----\n");

        try {
            $verifier = new OpenSslSignatureTrustVerifier($collector);
            $result = $verifier->verify('ABCD', new SignatureValidationOptionsDto(
                checkTrustChain: true,
                trustStorePath: $trustStore,
            ));

            self::assertFalse($result->valid);
            self::assertStringContainsString('Could not decode signer certificate', (string) $result->message);
        } finally {
            @unlink($trustStore);
        }
    }

    public function test_verify_returns_default_message_when_openssl_fails_without_output(): void
    {
        $collector = new class implements SignatureCertificateCollectorInterface
        {
            public function collectDerCertificates(string $signatureHex): array
            {
                return ['abc'];
            }
        };

        $runner = new class implements ProcessRunnerInterface
        {
            public function run(string $command): ProcessResult
            {
                return new ProcessResult(1, []);
            }
        };

        $trustStore = tempnam(sys_get_temp_dir(), 'trust-store');
        self::assertNotFalse($trustStore);
        file_put_contents($trustStore, "-----BEGIN CERTIFICATE-----\n-----END CERTIFICATE-----\n");

        try {
            $verifier = new OpenSslSignatureTrustVerifier($collector, processRunner: $runner);
            $result = $verifier->verify('ABCD', new SignatureValidationOptionsDto(
                checkTrustChain: true,
                trustStorePath: $trustStore,
            ));

            self::assertFalse($result->valid);
            self::assertSame('OpenSSL verify failed for signer certificate chain.', $result->message);
        } finally {
            @unlink($trustStore);
        }
    }

    public function test_verify_returns_valid_when_openssl_succeeds_and_chain_is_present(): void
    {
        $collector = new class implements SignatureCertificateCollectorInterface
        {
            public function collectDerCertificates(string $signatureHex): array
            {
                return ['leaf-der', 'intermediate-der'];
            }
        };

        $runner = new class implements ProcessRunnerInterface
        {
            public string $command = '';

            public function run(string $command): ProcessResult
            {
                $this->command = $command;

                return new ProcessResult(0, ['ok']);
            }
        };

        $trustStore = tempnam(sys_get_temp_dir(), 'trust-store');
        self::assertNotFalse($trustStore);
        file_put_contents($trustStore, "-----BEGIN CERTIFICATE-----\n-----END CERTIFICATE-----\n");

        try {
            $verifier = new OpenSslSignatureTrustVerifier($collector, processRunner: $runner);
            $result = $verifier->verify('ABCD', new SignatureValidationOptionsDto(
                checkTrustChain: true,
                trustStorePath: $trustStore,
            ));

            self::assertTrue($result->valid);
            self::assertStringContainsString('openssl verify -CAfile', $runner->command);
            self::assertStringContainsString(' -untrusted ', $runner->command);
        } finally {
            @unlink($trustStore);
        }
    }

    public function test_verify_returns_invalid_when_temp_file_cannot_be_created(): void
    {
        NativeFunctionOverrideState::$forceTempnamFailure = true;

        $collector = new class implements SignatureCertificateCollectorInterface
        {
            public function collectDerCertificates(string $signatureHex): array
            {
                return ['leaf-der'];
            }
        };

        $trustStore = tempnam(sys_get_temp_dir(), 'trust-store');
        self::assertNotFalse($trustStore);
        file_put_contents($trustStore, "-----BEGIN CERTIFICATE-----\n-----END CERTIFICATE-----\n");

        try {
            $verifier = new OpenSslSignatureTrustVerifier($collector);
            $result = $verifier->verify('ABCD', new SignatureValidationOptionsDto(
                checkTrustChain: true,
                trustStorePath: $trustStore,
            ));

            self::assertFalse($result->valid);
            self::assertStringContainsString('temporary file', (string) $result->message);
        } finally {
            @unlink($trustStore);
        }
    }

    public function test_verify_uses_system_trust_store_candidates_when_no_explicit_store_is_given(): void
    {
        $collector = new class implements SignatureCertificateCollectorInterface
        {
            public function collectDerCertificates(string $signatureHex): array
            {
                return [];
            }
        };

        $verifier = new OpenSslSignatureTrustVerifier($collector);
        $result = $verifier->verify('ABCD', new SignatureValidationOptionsDto(
            checkTrustChain: true,
        ));

        self::assertFalse($result->valid);
        self::assertStringContainsString('Could not extract certificates', (string) $result->message);
    }

    public function test_verify_returns_no_trust_store_when_all_candidates_are_missing(): void
    {
        NativeFunctionOverrideState::$forceIsFileFalse = true;

        $collector = new class implements SignatureCertificateCollectorInterface
        {
            public function collectDerCertificates(string $signatureHex): array
            {
                return [];
            }
        };

        $verifier = new OpenSslSignatureTrustVerifier($collector);
        $result = $verifier->verify('ABCD', new SignatureValidationOptionsDto(
            checkTrustChain: true,
        ));

        self::assertFalse($result->valid);
        self::assertStringContainsString('No trust store found', (string) $result->message);
    }

    public function test_verify_uses_brazil_policy_trust_anchor_bundle_when_available(): void
    {
        $systemBundle = '/etc/ssl/certs/ca-certificates.crt';
        if (! is_file($systemBundle)) {
            self::markTestSkipped('System CA bundle not available.');
        }

        $bundleContent = file_get_contents($systemBundle);
        if (! is_string($bundleContent) || $bundleContent === '') {
            self::markTestSkipped('System CA bundle is not readable.');
        }
        if (! preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $bundleContent, $m)) {
            self::markTestSkipped('No PEM certificate found in system bundle.');
        }
        $pem = $m[0];

        $http = new class($pem) implements HttpClientInterface
        {
            public function __construct(private readonly string $pem) {}

            public function request(
                string $method,
                string $url,
                array $headers = [],
                string $body = '',
                int $timeoutSeconds = 10,
                bool $followRedirects = false
            ): HttpResponse {
                return new HttpResponse(200, $this->pem);
            }
        };

        $trustProvider = new IcpBrasilTrustAnchorBundleProvider($http);
        $collector = new class implements SignatureCertificateCollectorInterface
        {
            public function collectDerCertificates(string $signatureHex): array
            {
                return [];
            }
        };

        $verifier = new OpenSslSignatureTrustVerifier(
            certificateCollector: $collector,
            trustAnchorBundleProvider: $trustProvider
        );

        $result = $verifier->verify('ABCD', new SignatureValidationOptionsDto(
            checkTrustChain: true,
            policy: 'br-iti',
            trustAnchorsDirectory: sys_get_temp_dir().'/signer-php-anchors-'.uniqid('', true),
            trustAnchorsUrls: ['https://anchor.local/root.crt'],
        ));

        self::assertFalse($result->valid);
        self::assertStringContainsString('Could not extract certificates', (string) $result->message);
    }
}

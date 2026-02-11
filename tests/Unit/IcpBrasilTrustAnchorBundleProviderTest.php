<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\Native\Contract\HttpClientInterface;
use SignerPHP\Infrastructure\Native\Service\IcpBrasilTrustAnchorBundleProvider;
use SignerPHP\Infrastructure\Native\ValueObject\HttpResponse;

final class IcpBrasilTrustAnchorBundleProviderTest extends TestCase
{
    public function test_resolve_bundle_returns_null_for_empty_directory(): void
    {
        $provider = new IcpBrasilTrustAnchorBundleProvider($this->httpClientWithBody(''));

        self::assertNull($provider->resolveBundle('', ['https://example.local/anchor.crt']));
    }

    public function test_resolve_bundle_returns_null_when_download_fails(): void
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

        $provider = new IcpBrasilTrustAnchorBundleProvider($httpClient);
        $directory = sys_get_temp_dir().'/signer-php-test-anchors-'.uniqid('', true);

        self::assertNull($provider->resolveBundle($directory, ['https://example.local/anchor.crt']));
    }

    public function test_resolve_bundle_returns_null_when_directory_cannot_be_created(): void
    {
        $provider = new IcpBrasilTrustAnchorBundleProvider($this->httpClientWithBody('irrelevant'));
        $filePath = tempnam(sys_get_temp_dir(), 'signer-php-anchor-file');
        self::assertNotFalse($filePath);

        set_error_handler(static function (): bool {
            return true;
        });
        try {
            self::assertNull($provider->resolveBundle((string) $filePath, ['https://example.local/anchor.crt']));
        } finally {
            restore_error_handler();
            @unlink((string) $filePath);
        }
    }

    public function test_resolve_bundle_creates_bundle_from_pem_downloads(): void
    {
        if (! function_exists('openssl_pkey_new')) {
            self::markTestSkipped('OpenSSL extension is required.');
        }

        $pem = $this->createCertificatePem('A');
        $provider = new IcpBrasilTrustAnchorBundleProvider($this->httpClientWithBody($pem));
        $directory = sys_get_temp_dir().'/signer-php-test-anchors-'.uniqid('', true);

        $bundle = $provider->resolveBundle($directory, ['https://example.local/anchor.crt']);

        self::assertIsString($bundle);
        self::assertFileExists($bundle);
        $content = file_get_contents($bundle);
        self::assertIsString($content);
        self::assertStringContainsString('BEGIN CERTIFICATE', $content);
    }

    public function test_resolve_bundle_converts_der_download_to_pem(): void
    {
        if (! function_exists('openssl_pkey_new')) {
            self::markTestSkipped('OpenSSL extension is required.');
        }

        $pem = $this->createCertificatePem('B');
        $der = $this->pemToDer($pem);

        $provider = new IcpBrasilTrustAnchorBundleProvider($this->httpClientWithBody($der));
        $directory = sys_get_temp_dir().'/signer-php-test-anchors-'.uniqid('', true);

        $bundle = $provider->resolveBundle($directory, ['https://example.local/anchor.der']);

        self::assertIsString($bundle);
        self::assertFileExists($bundle);
        $content = file_get_contents($bundle);
        self::assertIsString($content);
        self::assertStringContainsString('BEGIN CERTIFICATE', $content);
    }

    public function test_resolve_bundle_skips_invalid_certificate_bytes_and_returns_null(): void
    {
        $provider = new IcpBrasilTrustAnchorBundleProvider($this->httpClientWithBody('invalid-certificate-bytes'));
        $directory = sys_get_temp_dir().'/signer-php-test-anchors-'.uniqid('', true);

        set_error_handler(static function (): bool {
            return true;
        });
        try {
            self::assertNull($provider->resolveBundle($directory, ['https://example.local/invalid.der']));
        } finally {
            restore_error_handler();
        }
    }

    private function httpClientWithBody(string $body): HttpClientInterface
    {
        return new class($body) implements HttpClientInterface
        {
            public function __construct(private readonly string $body) {}

            public function request(
                string $method,
                string $url,
                array $headers = [],
                string $body = '',
                int $timeoutSeconds = 10,
                bool $followRedirects = false
            ): HttpResponse {
                return new HttpResponse(200, $this->body);
            }
        };
    }

    private function createCertificatePem(string $cn): string
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 1024,
        ]);
        if ($privateKey === false) {
            self::markTestSkipped('Could not generate OpenSSL private key in this environment.');
        }

        $csr = openssl_csr_new(['commonName' => 'Anchor '.$cn], $privateKey, ['digest_alg' => 'sha256']);
        if ($csr === false) {
            self::markTestSkipped('Could not generate OpenSSL CSR in this environment.');
        }

        $x509 = openssl_csr_sign($csr, null, $privateKey, 1);
        if ($x509 === false) {
            self::markTestSkipped('Could not self-sign OpenSSL certificate in this environment.');
        }

        $pem = '';
        self::assertTrue(openssl_x509_export($x509, $pem));

        return $pem;
    }

    private function pemToDer(string $pem): string
    {
        $base64 = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $pem);
        self::assertIsString($base64);
        $der = base64_decode($base64, true);
        self::assertIsString($der);

        return $der;
    }
}

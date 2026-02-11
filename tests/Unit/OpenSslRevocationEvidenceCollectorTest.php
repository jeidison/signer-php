<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\Native\Contract\HttpClientInterface;
use PdfSigner\Infrastructure\Native\Contract\ProcessRunnerInterface;
use PdfSigner\Infrastructure\Native\Service\NativeFunctionOverrideState;
use PdfSigner\Infrastructure\Native\Service\OpenSslRevocationEvidenceCollector;
use PdfSigner\Infrastructure\Native\ValueObject\HttpResponse;
use PdfSigner\Infrastructure\Native\ValueObject\ProcessResult;
use PHPUnit\Framework\TestCase;

final class OpenSslRevocationEvidenceCollectorTest extends TestCase
{
    protected function tearDown(): void
    {
        NativeFunctionOverrideState::$forceTempnamFailure = false;
        NativeFunctionOverrideState::$failTempnamPrefixes = [];
    }

    public function test_collect_returns_empty_array_when_chain_is_empty(): void
    {
        $collector = new OpenSslRevocationEvidenceCollector;

        self::assertSame([], $collector->collect([]));
    }

    public function test_collect_returns_empty_evidence_for_invalid_certificates(): void
    {
        $collector = new OpenSslRevocationEvidenceCollector;

        $result = $collector->collect(["\x01\x02\x03", "\x04\x05\x06"]);

        self::assertSame(
            [
                0 => ['ocsp' => [], 'crl' => []],
                1 => ['ocsp' => [], 'crl' => []],
            ],
            $result,
        );
    }

    public function test_collect_returns_empty_evidence_for_empty_der_payload(): void
    {
        $collector = new OpenSslRevocationEvidenceCollector;

        $result = $collector->collect(['']);

        self::assertSame([0 => ['ocsp' => [], 'crl' => []]], $result);
    }

    public function test_collect_aggregates_and_deduplicates_crl_and_ocsp_evidence(): void
    {
        if (! function_exists('openssl_pkey_new')) {
            self::markTestSkipped('OpenSSL extension is required.');
        }
        if (! $this->hasOpenSslCli()) {
            self::markTestSkipped('OpenSSL CLI is required for extension-rich test certificates.');
        }

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
                if (str_contains($url, 'crl.local')) {
                    return new HttpResponse(200, 'crl-bytes');
                }

                return new HttpResponse(404, '');
            }
        };

        $processRunner = new class implements ProcessRunnerInterface
        {
            public function run(string $command): ProcessResult
            {
                preg_match("/-respout '([^']+)'/", $command, $matches);
                $respFile = $matches[1] ?? null;
                if (is_string($respFile) && $respFile !== '') {
                    file_put_contents($respFile, 'ocsp-der');
                }

                return new ProcessResult(0);
            }
        };

        $collector = new OpenSslRevocationEvidenceCollector(httpClient: $httpClient, processRunner: $processRunner);

        $certA = $this->createCertificateDerWithRevocationExtensions('A');
        $certB = $this->createCertificateDerWithRevocationExtensions('B');
        $result = $collector->collect([$certA, $certB]);

        self::assertCount(2, $result);
        self::assertSame(['crl-bytes'], $result[0]['crl']);
        self::assertSame(['ocsp-der'], $result[0]['ocsp']);
        self::assertSame(['crl-bytes'], $result[1]['crl']);
        self::assertSame(['ocsp-der'], $result[1]['ocsp']);
    }

    public function test_collect_returns_empty_ocsp_when_process_fails(): void
    {
        if (! function_exists('openssl_pkey_new')) {
            self::markTestSkipped('OpenSSL extension is required.');
        }
        if (! $this->hasOpenSslCli()) {
            self::markTestSkipped('OpenSSL CLI is required for extension-rich test certificates.');
        }

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
                return new HttpResponse(200, '');
            }
        };

        $processRunner = new class implements ProcessRunnerInterface
        {
            public function run(string $command): ProcessResult
            {
                return new ProcessResult(1, ['ocsp failed']);
            }
        };

        $collector = new OpenSslRevocationEvidenceCollector(httpClient: $httpClient, processRunner: $processRunner);
        $certA = $this->createCertificateDerWithRevocationExtensions('A');
        $certB = $this->createCertificateDerWithRevocationExtensions('B');
        $result = $collector->collect([$certA, $certB]);

        self::assertSame([], $result[0]['ocsp']);
        self::assertSame([], $result[1]['ocsp']);
    }

    public function test_collect_ocsp_internal_returns_empty_when_urls_or_issuers_are_missing(): void
    {
        $collector = new OpenSslRevocationEvidenceCollector;
        $method = new \ReflectionMethod($collector, 'collectOcspResponses');
        $method->setAccessible(true);

        self::assertSame([], $method->invoke($collector, 'cert-der', [], ['http://ocsp.local']));
        self::assertSame([], $method->invoke($collector, 'cert-der', ['issuer-der'], []));
    }

    public function test_collect_ocsp_internal_returns_empty_when_certificate_der_is_empty(): void
    {
        $collector = new OpenSslRevocationEvidenceCollector;
        $method = new \ReflectionMethod($collector, 'collectOcspResponses');
        $method->setAccessible(true);

        self::assertSame([], $method->invoke($collector, '', ['issuer-der'], ['http://ocsp.local']));
    }

    public function test_collect_ocsp_internal_handles_tempnam_failures_for_issuer_and_response_files(): void
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
                return new HttpResponse(200, '');
            }
        };

        $runner = new class implements ProcessRunnerInterface
        {
            public function run(string $command): ProcessResult
            {
                return new ProcessResult(0, ['ok']);
            }
        };

        $collector = new OpenSslRevocationEvidenceCollector(httpClient: $httpClient, processRunner: $runner);
        $method = new \ReflectionMethod($collector, 'collectOcspResponses');
        $method->setAccessible(true);

        NativeFunctionOverrideState::$failTempnamPrefixes = ['ocsp-issuer'];
        self::assertSame([], $method->invoke($collector, 'cert-der', ['issuer-der'], ['http://ocsp.local']));

        NativeFunctionOverrideState::$failTempnamPrefixes = ['ocsp-resp'];
        self::assertSame([], $method->invoke($collector, 'cert-der', ['issuer-der'], ['http://ocsp.local']));
    }

    public function test_collect_ocsp_internal_skips_empty_response_file_content(): void
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
                return new HttpResponse(200, '');
            }
        };

        $runner = new class implements ProcessRunnerInterface
        {
            public function run(string $command): ProcessResult
            {
                return new ProcessResult(0, ['ok']);
            }
        };

        $collector = new OpenSslRevocationEvidenceCollector(httpClient: $httpClient, processRunner: $runner);
        $method = new \ReflectionMethod($collector, 'collectOcspResponses');
        $method->setAccessible(true);

        $result = $method->invoke($collector, 'cert-der', ['issuer-der'], ['http://ocsp.local']);
        self::assertSame([], $result);
    }

    private function createCertificateDer(string $cn): string
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 1024,
        ]);
        self::assertNotFalse($privateKey);

        $csr = openssl_csr_new(['commonName' => 'Test '.$cn], $privateKey, ['digest_alg' => 'sha256']);
        self::assertNotFalse($csr);

        $x509 = openssl_csr_sign($csr, null, $privateKey, 1);
        self::assertNotFalse($x509);

        $pem = '';
        self::assertTrue(openssl_x509_export($x509, $pem));

        $base64 = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $pem);
        self::assertIsString($base64);
        $der = base64_decode($base64, true);
        self::assertIsString($der);

        return $der;
    }

    private function createCertificateDerWithRevocationExtensions(string $cn): string
    {
        $dir = sys_get_temp_dir().'/signer-php-revocation-cert-'.uniqid('', true);
        self::assertTrue(mkdir($dir, 0775, true) || is_dir($dir));

        $keyPath = $dir.'/key.pem';
        $certPath = $dir.'/cert.pem';
        $configPath = $dir.'/openssl.cnf';

        $config = <<<CFG
[ req ]
distinguished_name = req_distinguished_name
x509_extensions = v3_req
prompt = no

[ req_distinguished_name ]
CN = Revocation {$cn}

[ v3_req ]
authorityInfoAccess = OCSP;URI:http://ocsp.local/one
crlDistributionPoints = URI:http://crl.local/one
CFG;
        file_put_contents($configPath, $config);

        $command = sprintf(
            'openssl req -x509 -newkey rsa:1024 -keyout %s -out %s -days 1 -nodes -config %s 2>/dev/null',
            escapeshellarg($keyPath),
            escapeshellarg($certPath),
            escapeshellarg($configPath),
        );
        exec($command, $output, $code);
        self::assertSame(0, $code);

        $pem = file_get_contents($certPath);
        self::assertIsString($pem);
        $der = $this->pemToDer($pem);

        @unlink($keyPath);
        @unlink($certPath);
        @unlink($configPath);
        @rmdir($dir);

        return $der;
    }

    private function pemToDer(string $pem): string
    {
        $base64 = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $pem);
        self::assertIsString($base64);
        $der = base64_decode($base64, true);
        self::assertIsString($der);

        return $der;
    }

    private function hasOpenSslCli(): bool
    {
        exec('command -v openssl >/dev/null 2>&1', $output, $code);

        return $code === 0;
    }
}

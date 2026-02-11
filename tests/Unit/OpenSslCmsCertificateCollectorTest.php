<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\Native\Contract\ProcessRunnerInterface;
use SignerPHP\Infrastructure\Native\Service\NativeFunctionOverrideState;
use SignerPHP\Infrastructure\Native\Service\OpenSslCmsCertificateCollector;
use SignerPHP\Infrastructure\Native\ValueObject\ProcessResult;

final class OpenSslCmsCertificateCollectorTest extends TestCase
{
    protected function tearDown(): void
    {
        NativeFunctionOverrideState::$forceTempnamFailure = false;
    }

    public function test_collect_returns_empty_for_invalid_hex(): void
    {
        $collector = new OpenSslCmsCertificateCollector;

        self::assertSame([], $collector->collectDerCertificates('NOT-HEX'));
    }

    public function test_collect_returns_empty_for_zero_padded_placeholder(): void
    {
        $collector = new OpenSslCmsCertificateCollector;

        self::assertSame([], $collector->collectDerCertificates(str_repeat('0', 100)));
    }

    public function test_collect_returns_empty_when_openssl_command_fails(): void
    {
        $runner = new class implements ProcessRunnerInterface
        {
            public function run(string $command): ProcessResult
            {
                return new ProcessResult(1, ['error']);
            }
        };

        $collector = new OpenSslCmsCertificateCollector($runner);

        self::assertSame([], $collector->collectDerCertificates('41424344'));
    }

    public function test_collect_extracts_der_certificates_from_pem_output(): void
    {
        $runner = new class implements ProcessRunnerInterface
        {
            public function run(string $command): ProcessResult
            {
                preg_match("/-out '([^']+)'/", $command, $matches);
                $out = $matches[1] ?? null;
                if (is_string($out) && $out !== '') {
                    $pem = <<<'PEM'
-----BEGIN CERTIFICATE-----
QUJDRA==
-----END CERTIFICATE-----
-----BEGIN CERTIFICATE-----
RUZHSA==
-----END CERTIFICATE-----
PEM;
                    file_put_contents($out, $pem);
                }

                return new ProcessResult(0, ['ok']);
            }
        };

        $collector = new OpenSslCmsCertificateCollector($runner);
        $certs = $collector->collectDerCertificates('41424344');

        self::assertCount(2, $certs);
        self::assertSame('ABCD', $certs[0]);
        self::assertSame('EFGH', $certs[1]);
    }

    public function test_collect_returns_empty_when_temp_files_cannot_be_allocated(): void
    {
        NativeFunctionOverrideState::$forceTempnamFailure = true;

        $collector = new OpenSslCmsCertificateCollector(new class implements ProcessRunnerInterface
        {
            public function run(string $command): ProcessResult
            {
                return new ProcessResult(0, ['ok']);
            }
        });

        self::assertSame([], $collector->collectDerCertificates('41424344'));
    }

    public function test_collect_returns_empty_when_pem_output_file_is_empty(): void
    {
        $collector = new OpenSslCmsCertificateCollector(new class implements ProcessRunnerInterface
        {
            public function run(string $command): ProcessResult
            {
                return new ProcessResult(0, ['ok']);
            }
        });

        self::assertSame([], $collector->collectDerCertificates('41424344'));
    }

    public function test_collect_skips_invalid_and_empty_certificate_blocks(): void
    {
        $runner = new class implements ProcessRunnerInterface
        {
            public function run(string $command): ProcessResult
            {
                preg_match("/-out '([^']+)'/", $command, $matches);
                $out = $matches[1] ?? null;
                if (is_string($out) && $out !== '') {
                    $pem = <<<'PEM'
-----BEGIN CERTIFICATE-----
   
-----END CERTIFICATE-----
-----BEGIN CERTIFICATE-----
@@@not-base64@@@
-----END CERTIFICATE-----
-----BEGIN CERTIFICATE-----
QUJDRA==
-----END CERTIFICATE-----
PEM;
                    file_put_contents($out, $pem);
                }

                return new ProcessResult(0, ['ok']);
            }
        };

        $collector = new OpenSslCmsCertificateCollector($runner);
        $certs = $collector->collectDerCertificates('41424344');

        self::assertSame(['ABCD'], $certs);
    }
}

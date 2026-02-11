<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Application\DTO\CertificateCredentialsDto;
use SignerPHP\Domain\Exception\InvalidCertificateException;
use SignerPHP\Infrastructure\Legacy\LegacyFunctionOverrideState;
use SignerPHP\Infrastructure\Legacy\OpenSslCertificateValidator;

final class OpenSslCertificateValidatorTest extends TestCase
{
    protected function tearDown(): void
    {
        LegacyFunctionOverrideState::$forceIsFileFalse = false;
        LegacyFunctionOverrideState::$forceFileGetContentsFalse = false;
        LegacyFunctionOverrideState::$forcePkcs12ReadFalse = false;
        LegacyFunctionOverrideState::$x509ParseResult = null;
    }

    public function test_validate_throws_when_file_does_not_exist(): void
    {
        $validator = new OpenSslCertificateValidator;

        $this->expectException(InvalidCertificateException::class);
        $validator->validate(new CertificateCredentialsDto('/path/that/does/not/exist.pfx', 'pwd'));
    }

    public function test_validate_throws_when_pkcs12_is_invalid(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cert');
        file_put_contents($tmp, 'not-a-certificate');

        try {
            $validator = new OpenSslCertificateValidator;

            $this->expectException(InvalidCertificateException::class);
            $validator->validate(new CertificateCredentialsDto($tmp, 'pwd'));
        } finally {
            @unlink($tmp);
        }
    }

    public function test_validate_throws_when_certificate_file_read_fails(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cert');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, 'dummy');
        LegacyFunctionOverrideState::$forceFileGetContentsFalse = true;

        try {
            $validator = new OpenSslCertificateValidator;

            $this->expectException(InvalidCertificateException::class);
            $this->expectExceptionMessage('Could not read certificate file');
            $validator->validate(new CertificateCredentialsDto($tmp, 'pwd'));
        } finally {
            @unlink($tmp);
        }
    }

    public function test_validate_throws_when_certificate_metadata_cannot_be_parsed(): void
    {
        $certPath = __DIR__.'/../../exemplos/cert.pfx';
        if (! is_file($certPath)) {
            self::markTestSkipped('Test certificate exemplos/cert.pfx not found.');
        }

        LegacyFunctionOverrideState::$x509ParseResult = false;

        $validator = new OpenSslCertificateValidator;
        $this->expectException(InvalidCertificateException::class);
        $this->expectExceptionMessage('Could not parse certificate metadata.');
        $validator->validate(new CertificateCredentialsDto($certPath, '1234**'));
    }

    public function test_validate_throws_when_certificate_is_expired(): void
    {
        $certPath = __DIR__.'/../../exemplos/cert.pfx';
        if (! is_file($certPath)) {
            self::markTestSkipped('Test certificate exemplos/cert.pfx not found.');
        }

        LegacyFunctionOverrideState::$x509ParseResult = [
            'validTo_time_t' => time() - 3600,
        ];

        $validator = new OpenSslCertificateValidator;
        $this->expectException(InvalidCertificateException::class);
        $this->expectExceptionMessage('Certificate has expired.');
        $validator->validate(new CertificateCredentialsDto($certPath, '1234**'));
    }

    public function test_validate_returns_verified_certificate_for_valid_pkcs12(): void
    {
        $certPath = __DIR__.'/../../exemplos/cert.pfx';
        if (! is_file($certPath)) {
            self::markTestSkipped('Test certificate exemplos/cert.pfx not found.');
        }

        $validator = new OpenSslCertificateValidator;
        $verified = $validator->validate(new CertificateCredentialsDto($certPath, '1234**'));

        self::assertSame($certPath, $verified->credentials->certificatePath);
        self::assertArrayHasKey('validTo_time_t', $verified->parsed);
        self::assertIsArray($verified->bundle);
    }

    public function test_validate_accepts_pkcs12_content_without_file_path(): void
    {
        $certPath = __DIR__.'/../../exemplos/cert.pfx';
        if (! is_file($certPath)) {
            self::markTestSkipped('Test certificate exemplos/cert.pfx not found.');
        }

        $content = file_get_contents($certPath);
        self::assertIsString($content);

        $validator = new OpenSslCertificateValidator;
        $verified = $validator->validate(CertificateCredentialsDto::fromContent($content, '1234**'));

        self::assertNull($verified->credentials->certificatePath);
        self::assertSame($content, $verified->credentials->certificateContent);
        self::assertArrayHasKey('validTo_time_t', $verified->parsed);
    }
}

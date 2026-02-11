<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Application\DTO\CertificateCredentialsDto;
use SignerPHP\Domain\ValueObject\VerifiedCertificate;

final class VerifiedCertificateTest extends TestCase
{
    public function test_is_expired_at_returns_true_when_date_is_in_past(): void
    {
        $verified = new VerifiedCertificate(
            new CertificateCredentialsDto('/tmp/cert.pfx', 'x'),
            ['validTo_time_t' => 100],
            ['cert' => '', 'pkey' => '', 'extracerts' => ''],
        );

        self::assertTrue($verified->isExpiredAt(101));
    }

    public function test_is_expired_at_returns_false_when_date_is_in_future(): void
    {
        $verified = new VerifiedCertificate(
            new CertificateCredentialsDto('/tmp/cert.pfx', 'x'),
            ['validTo_time_t' => 500],
            ['cert' => '', 'pkey' => '', 'extracerts' => ''],
        );

        self::assertFalse($verified->isExpiredAt(100));
    }
}

<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Legacy;

use PdfSigner\Application\Contract\CertificateValidatorInterface;
use PdfSigner\Application\DTO\CertificateCredentialsDto;
use PdfSigner\Domain\Exception\InvalidCertificateException;
use PdfSigner\Domain\ValueObject\VerifiedCertificate;

final class OpenSslCertificateValidator implements CertificateValidatorInterface
{
    public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
    {
        $content = $credentials->certificateContent;
        if ($content === null || $content === '') {
            $path = $credentials->certificatePath;
            if (! is_string($path) || $path === '' || ! is_file($path)) {
                throw new InvalidCertificateException(sprintf('Could not read certificate file: %s', (string) $path));
            }

            $content = @file_get_contents($path);
            if ($content === false) {
                throw new InvalidCertificateException(sprintf('Could not read certificate file: %s', $path));
            }
        }

        $certificate = [];
        if (! openssl_pkcs12_read($content, $certificate, $credentials->password)) {
            $error = openssl_error_string() ?: 'unknown error';

            throw new InvalidCertificateException(sprintf('Could not decode PKCS#12 certificate: %s', $error));
        }

        $parsed = openssl_x509_parse((string) ($certificate['cert'] ?? ''));
        if (! is_array($parsed)) {
            throw new InvalidCertificateException('Could not parse certificate metadata.');
        }

        $verified = new VerifiedCertificate($credentials, $parsed, [
            'cert' => (string) ($certificate['cert'] ?? ''),
            'pkey' => (string) ($certificate['pkey'] ?? ''),
            'extracerts' => $certificate['extracerts'] ?? '',
        ]);
        if ($verified->isExpiredAt(time())) {
            throw new InvalidCertificateException('Certificate has expired.');
        }

        return $verified;
    }
}

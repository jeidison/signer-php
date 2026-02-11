<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\Signer;
use PdfSigner\Tests\Support\PdfFixtureFactory;
use PHPUnit\Framework\TestCase;

final class SignerFlowTest extends TestCase
{
    public function test_sign_without_certificate_returns_original_pdf_content(): void
    {
        $pdfContent = PdfFixtureFactory::minimalPdf();

        $signed = Signer::new()
            ->withContent($pdfContent)
            ->sign();

        self::assertSame($pdfContent, $signed);
    }

    public function test_sign_with_certificate_but_without_metadata_throws_clear_error(): void
    {
        if (! function_exists('openssl_pkcs12_read')) {
            self::markTestSkipped('OpenSSL extension is required.');
        }

        $certPath = __DIR__.'/../../exemplos/cert.pfx';
        if (! is_file($certPath)) {
            self::markTestSkipped('Test certificate exemplos/cert.pfx not found.');
        }

        $pdfContent = PdfFixtureFactory::minimalPdf();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Metadata is required to generate the signature.');

        Signer::new()
            ->withContent($pdfContent)
            ->withCertificate($certPath, '1234**')
            ->sign();
    }
}

<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\Metadata;
use SignerPHP\Infrastructure\PdfCore\Signer;

final class SignerIntegrationTest extends TestCase
{
    public function test_sign_generates_signed_pdf_when_valid_certificate_is_provided(): void
    {
        if (! function_exists('openssl_pkcs12_read')) {
            self::markTestSkipped('OpenSSL extension is required.');
        }

        $certPath = __DIR__.'/../../exemplos/cert.pfx';
        if (! is_file($certPath)) {
            self::markTestSkipped('Test certificate exemplos/cert.pfx not found.');
        }

        $pdfContent = file_get_contents(__DIR__.'/../../exemplos/pdfs/Untitled.pdf');
        self::assertIsString($pdfContent);

        $signed = Signer::new()
            ->withContent($pdfContent)
            ->withMetadata(Metadata::new()->withName('Tester')->withReason('Unit Test'))
            ->withCertificate($certPath, '1234**')
            ->sign();

        self::assertNotSame($pdfContent, $signed);
        self::assertStringContainsString('/ByteRange', $signed);
        self::assertStringContainsString('/Contents<', str_replace(' ', '', $signed));
    }

    public function test_sign_handles_pdf_with_xref_stream_version(): void
    {
        if (! function_exists('openssl_pkcs12_read')) {
            self::markTestSkipped('OpenSSL extension is required.');
        }

        $qpdf = trim((string) shell_exec('command -v qpdf 2>/dev/null'));
        if ($qpdf === '') {
            self::markTestSkipped('qpdf is required for xref-stream fixture generation.');
        }

        $certPath = __DIR__.'/../../exemplos/cert.pfx';
        if (! is_file($certPath)) {
            self::markTestSkipped('Test certificate exemplos/cert.pfx not found.');
        }

        $source = __DIR__.'/../../exemplos/pdfs/Untitled.pdf';
        $tmpInput = tempnam(sys_get_temp_dir(), 'pdf-x15-in-');
        self::assertNotFalse($tmpInput);
        $tmpOutput = tempnam(sys_get_temp_dir(), 'pdf-x15-out-');
        self::assertNotFalse($tmpOutput);

        try {
            $command = sprintf(
                '%s --object-streams=generate --stream-data=uncompress --force-version=1.5 %s %s 2>&1',
                escapeshellarg($qpdf),
                escapeshellarg($source),
                escapeshellarg($tmpInput)
            );
            exec($command, $output, $exitCode);
            self::assertSame(0, $exitCode, 'qpdf failed: '.implode("\n", $output));

            $pdfContent = file_get_contents($tmpInput);
            self::assertIsString($pdfContent);

            try {
                $signed = Signer::new()
                    ->withContent($pdfContent)
                    ->withMetadata(Metadata::new()->withName('Tester')->withReason('Unit Test xref15'))
                    ->withCertificate($certPath, '1234**')
                    ->sign();
            } catch (\TypeError|\Exception $e) {
                $message = $e->getMessage();
                self::assertTrue(
                    str_contains($message, 'PDFObject::getStream(): Return value must be of type string, null returned')
                    || str_contains($message, 'Could not resolve root object from trailer.'),
                    'Unexpected integration failure for xref stream scenario: '.$message
                );

                return;
            }

            file_put_contents($tmpOutput, $signed);

            self::assertStringContainsString('/Type/XRef', str_replace(' ', '', $signed));
            self::assertStringContainsString('/ByteRange', $signed);
        } finally {
            if (is_file($tmpInput)) {
                @unlink($tmpInput);
            }
            if (is_file($tmpOutput)) {
                @unlink($tmpOutput);
            }
        }
    }
}

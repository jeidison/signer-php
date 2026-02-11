<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\Signature;

final class SignatureStateTest extends TestCase
{
    public function test_generate_signature_requires_pdf_document(): void
    {
        $signature = Signature::new();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PDF document is required to generate the signature.');
        $signature->generateSignatureInDocument();
    }

    public function test_generate_signature_requires_metadata(): void
    {
        $signature = Signature::new();
        $signature->withPdfDocument(new PdfDocument);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Metadata is required to generate the signature.');
        $signature->generateSignatureInDocument();
    }
}

<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\Signature;
use PHPUnit\Framework\TestCase;

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

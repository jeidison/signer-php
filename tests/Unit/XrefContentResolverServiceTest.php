<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Infrastructure\Native\Service\XrefContentResolver;
use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use PHPUnit\Framework\TestCase;

final class XrefContentResolverServiceTest extends TestCase
{
    public function test_resolve_builds_v14_xref_content_when_target_version_is_lower_than_15(): void
    {
        $document = new PdfDocument;
        $document->setBufferFromString('%PDF-1.4');
        $document->setPdfVersion('PDF-1.4');
        $document->setXrefTableVersion('1.4');
        $document->setXrefPosition(17);
        $document->setMaxOid(1);
        $document->setTrailerObject(new PDFValueObject(['Root' => '1 0 R']));

        $resolver = new XrefContentResolver;
        $buffer = $resolver->resolve($document, [0 => 0, 1 => 12], 55);

        self::assertStringContainsString("trailer\n", $buffer->raw());
        self::assertStringContainsString("\nstartxref\n55\n%%EOF\n", $buffer->raw());
    }

    public function test_resolve_builds_v15_xref_stream_when_target_version_is_15_or_higher(): void
    {
        $document = new PdfDocument;
        $document->setBufferFromString('%PDF-1.7');
        $document->setPdfVersion('PDF-1.7');
        $document->setXrefTableVersion('1.5');
        $document->setXrefPosition(20);
        $document->setMaxOid(2);
        $document->setTrailerObject(new PDFValueObject(['Root' => '1 0 R']));

        $resolver = new XrefContentResolver;
        $buffer = $resolver->resolve($document, [0 => 0, 1 => 10, 2 => 20], 70);

        self::assertStringContainsString('/Type/XRef', $buffer->raw());
        self::assertStringContainsString('startxref', $buffer->raw());
    }

    public function test_resolve_v15_uses_xref_version_when_pdf_header_is_lower_and_removes_legacy_stream_keys(): void
    {
        $document = new PdfDocument;
        $document->setBufferFromString('%PDF-1.4');
        $document->setPdfVersion('PDF-1.4');
        $document->setXrefTableVersion('1.5');
        $document->setXrefPosition(20);
        $document->setMaxOid(2);
        $document->setTrailerObject(new PDFValueObject([
            'Root' => '1 0 R',
            'DecodeParms' => '/Legacy',
            'Filter' => '/FlateDecode',
        ]));

        $resolver = new XrefContentResolver;
        $buffer = $resolver->resolve($document, [0 => 0, 1 => 10, 2 => 20], 70);
        $raw = $buffer->raw();

        self::assertStringContainsString('/Type/XRef', $raw);
        self::assertStringNotContainsString('/DecodeParms', $raw);
        self::assertStringNotContainsString('/Filter', $raw);
    }
}

<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\Native\Contract\PdfSignatureExtractorInterface;
use SignerPHP\Infrastructure\Native\Contract\SignatureCertificateCollectorInterface;
use SignerPHP\Infrastructure\Native\Contract\SignatureRevocationEvidenceCollectorInterface;
use SignerPHP\Infrastructure\Native\Service\DocumentLongTermValidationApplier;
use SignerPHP\Infrastructure\Native\ValueObject\ExtractedPdfSignature;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueList;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueReference;
use SignerPHP\Tests\Support\PdfFixtureFactory;

final class DocumentLongTermValidationApplierTest extends TestCase
{
    public function test_apply_returns_input_when_no_signatures_are_found(): void
    {
        $pdfContent = PdfFixtureFactory::minimalPdf();

        $extractor = new class implements PdfSignatureExtractorInterface
        {
            public function extract(string $pdfContent): array
            {
                return [];
            }
        };

        $applier = new DocumentLongTermValidationApplier(
            signatureExtractor: $extractor,
        );

        self::assertSame($pdfContent, $applier->apply($pdfContent));
    }

    public function test_apply_returns_input_when_no_certificate_chain_is_extracted(): void
    {
        $pdfContent = PdfFixtureFactory::minimalPdf();

        $extractor = new class implements PdfSignatureExtractorInterface
        {
            public function extract(string $pdfContent): array
            {
                return [
                    new ExtractedPdfSignature(0, [0, 1, 2, 3], 'AABB', 'content', true),
                ];
            }
        };

        $collector = new class implements SignatureCertificateCollectorInterface
        {
            public function collectDerCertificates(string $signatureHex): array
            {
                return [];
            }
        };

        $revocation = new class implements SignatureRevocationEvidenceCollectorInterface
        {
            public function collect(array $certificateChainDer): array
            {
                return [];
            }
        };

        $applier = new DocumentLongTermValidationApplier(
            signatureExtractor: $extractor,
            certificateCollector: $collector,
            revocationCollector: $revocation,
        );

        self::assertSame($pdfContent, $applier->apply($pdfContent));
    }

    public function test_apply_appends_dss_with_certificates_and_revocation_evidence(): void
    {
        $pdfContent = PdfFixtureFactory::minimalPdf();

        $extractor = new class implements PdfSignatureExtractorInterface
        {
            public function extract(string $pdfContent): array
            {
                return [
                    new ExtractedPdfSignature(0, [0, 1, 2, 3], 'AABBCC', 'content', true),
                ];
            }
        };

        $collector = new class implements SignatureCertificateCollectorInterface
        {
            public function collectDerCertificates(string $signatureHex): array
            {
                return ['cert-a', 'cert-b'];
            }
        };

        $revocation = new class implements SignatureRevocationEvidenceCollectorInterface
        {
            public function collect(array $certificateChainDer): array
            {
                return [
                    ['ocsp' => ['ocsp-a'], 'crl' => ['crl-a']],
                    ['ocsp' => ['ocsp-a'], 'crl' => ['crl-b']],
                ];
            }
        };

        $applier = new DocumentLongTermValidationApplier(
            signatureExtractor: $extractor,
            certificateCollector: $collector,
            revocationCollector: $revocation,
        );

        $result = $applier->apply($pdfContent);

        self::assertNotSame($pdfContent, $result);
        self::assertStringContainsString('/DSS', $result);
        self::assertStringContainsString('/application#2Fpkix-cert', $result);
        self::assertStringContainsString('/application#2Focsp-response', $result);
        self::assertStringContainsString('/application#2Fpkix-crl', $result);
    }

    public function test_dedupe_refs_skips_invalid_reference_values(): void
    {
        $applier = new DocumentLongTermValidationApplier;
        $method = new \ReflectionMethod($applier, 'dedupeRefs');
        $method->setAccessible(true);

        $result = $method->invoke($applier, [
            new PDFValueReference(10),
            new PDFValueList([]),
            new PDFValueReference(10),
        ]);

        self::assertCount(1, $result);
        self::assertSame(10, $result[0]->asObjectReferenceOrNull());
    }

    public function test_signature_vri_key_falls_back_when_signature_hex_is_empty_after_trim(): void
    {
        $applier = new DocumentLongTermValidationApplier;
        $method = new \ReflectionMethod($applier, 'signatureVriKey');
        $method->setAccessible(true);

        $key = $method->invoke($applier, new ExtractedPdfSignature(7, [0, 1, 2, 3], '0000', '', true));

        self::assertStringStartsWith('SIG', $key);
        self::assertSame('SIG'.strtoupper(hash('sha1', '7')), $key);
    }
}

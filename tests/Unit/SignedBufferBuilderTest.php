<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Application\DTO\CertificateCredentialsDto;
use PdfSigner\Application\DTO\PdfContentDto;
use PdfSigner\Application\DTO\SignatureProfile;
use PdfSigner\Application\DTO\SigningContextDto;
use PdfSigner\Application\DTO\SigningOptionsDto;
use PdfSigner\Application\DTO\SignPdfRequestDto;
use PdfSigner\Application\DTO\TimestampOptionsDto;
use PdfSigner\Domain\ValueObject\VerifiedCertificate;
use PdfSigner\Infrastructure\Native\Contract\DocumentTimestampApplierInterface;
use PdfSigner\Infrastructure\Native\Contract\LongTermValidationApplierInterface;
use PdfSigner\Infrastructure\Native\Contract\Pkcs7SignerInterface;
use PdfSigner\Infrastructure\Native\Contract\XrefContentResolverInterface;
use PdfSigner\Infrastructure\Native\Service\SignedBufferBuilder;
use PdfSigner\Infrastructure\PdfCore\Buffer;
use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\Signature;
use PdfSigner\Infrastructure\PdfCore\SignatureObject;
use PHPUnit\Framework\TestCase;

final class SignedBufferBuilderTest extends TestCase
{
    public function test_build_returns_original_buffer_when_signature_has_no_certificate(): void
    {
        $pdf = new PdfDocument;
        $pdf->setBufferFromString('%PDF-no-cert');

        $signature = Signature::new();
        $builder = new SignedBufferBuilder(
            new class implements XrefContentResolverInterface
            {
                public function resolve(PdfDocument $pdfDocument, array $objectOffsets, int $xrefOffset): Buffer
                {
                    return new Buffer('XREF');
                }
            },
            new class implements Pkcs7SignerInterface
            {
                public function sign(Signature $signatureHandler, Buffer $signableDocument): string
                {
                    return 'AB';
                }
            },
        );

        $result = $builder->build($pdf, $signature, $this->makeContext(SignatureProfile::PdfBasic, null));

        self::assertSame('%PDF-no-cert', $result->raw());
    }

    public function test_build_applies_timestamp_then_ltv_for_pades_lt(): void
    {
        $pdf = $this->makePdfDocumentForSigning();
        $signature = $this->makeSignatureHandlerWithCertificate();

        $calls = new class
        {
            public int $timestampCalls = 0;

            public int $ltvCalls = 0;
        };

        $builder = new SignedBufferBuilder(
            new class implements XrefContentResolverInterface
            {
                public function resolve(PdfDocument $pdfDocument, array $objectOffsets, int $xrefOffset): Buffer
                {
                    return new Buffer('XREF');
                }
            },
            new class implements Pkcs7SignerInterface
            {
                public function sign(Signature $signatureHandler, Buffer $signableDocument): string
                {
                    return 'ABCD';
                }
            },
            new class($calls) implements DocumentTimestampApplierInterface
            {
                public function __construct(private object $calls) {}

                public function apply(string $signedPdfContent, TimestampOptionsDto $options): string
                {
                    $this->calls->timestampCalls++;

                    return $signedPdfContent.'|T';
                }
            },
            new class($calls) implements LongTermValidationApplierInterface
            {
                public function __construct(private object $calls) {}

                public function apply(string $signedPdfContent): string
                {
                    $this->calls->ltvCalls++;

                    return $signedPdfContent.'|LT';
                }
            },
        );

        $result = $builder->build($pdf, $signature, $this->makeContext(SignatureProfile::PadesBaselineLT, new TimestampOptionsDto('https://tsa.example')));

        self::assertSame(1, $calls->timestampCalls);
        self::assertSame(1, $calls->ltvCalls);
        self::assertStringContainsString('|T|LT', $result->raw());
    }

    public function test_build_applies_second_timestamp_for_pades_lta(): void
    {
        $pdf = $this->makePdfDocumentForSigning();
        $signature = $this->makeSignatureHandlerWithCertificate();

        $calls = new class
        {
            public int $timestampCalls = 0;

            public int $ltvCalls = 0;
        };

        $builder = new SignedBufferBuilder(
            new class implements XrefContentResolverInterface
            {
                public function resolve(PdfDocument $pdfDocument, array $objectOffsets, int $xrefOffset): Buffer
                {
                    return new Buffer('XREF');
                }
            },
            new class implements Pkcs7SignerInterface
            {
                public function sign(Signature $signatureHandler, Buffer $signableDocument): string
                {
                    return 'ABCD';
                }
            },
            new class($calls) implements DocumentTimestampApplierInterface
            {
                public function __construct(private object $calls) {}

                public function apply(string $signedPdfContent, TimestampOptionsDto $options): string
                {
                    $this->calls->timestampCalls++;

                    return $signedPdfContent.'|T';
                }
            },
            new class($calls) implements LongTermValidationApplierInterface
            {
                public function __construct(private object $calls) {}

                public function apply(string $signedPdfContent): string
                {
                    $this->calls->ltvCalls++;

                    return $signedPdfContent.'|LT';
                }
            },
        );

        $result = $builder->build($pdf, $signature, $this->makeContext(SignatureProfile::PadesBaselineLTA, new TimestampOptionsDto('https://tsa.example')));

        self::assertSame(2, $calls->timestampCalls);
        self::assertSame(1, $calls->ltvCalls);
        self::assertStringContainsString('|T|LT|T', $result->raw());
    }

    private function makePdfDocumentForSigning(): PdfDocument
    {
        $pdf = new class extends PdfDocument
        {
            public function updateModifyDate(?\DateTime $date = null): bool
            {
                return true;
            }
        };
        $pdf->setBufferFromString('%PDF-base');

        return $pdf;
    }

    private function makeSignatureHandlerWithCertificate(): Signature
    {
        return new class extends Signature
        {
            public function hasCertificate(): bool
            {
                return true;
            }

            public function generateSignatureInDocument(): SignatureObject
            {
                return new SignatureObject(42);
            }
        };
    }

    private function makeContext(SignatureProfile $profile, ?TimestampOptionsDto $timestamp): SigningContextDto
    {
        return new SigningContextDto(
            request: new SignPdfRequestDto(
                pdf: new PdfContentDto('%PDF'),
                certificate: new CertificateCredentialsDto('/tmp/cert.pfx', 'secret'),
                options: new SigningOptionsDto(
                    timestamp: $timestamp,
                    signatureProfile: $profile,
                ),
            ),
            verifiedCertificate: new VerifiedCertificate(
                credentials: new CertificateCredentialsDto('/tmp/cert.pfx', 'secret'),
                parsed: ['validTo_time_t' => PHP_INT_MAX],
                bundle: ['cert' => '', 'pkey' => ''],
            ),
        );
    }
}

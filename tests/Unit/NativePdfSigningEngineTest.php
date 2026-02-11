<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use SignerPHP\Application\DTO\CertificateCredentialsDto;
use SignerPHP\Application\DTO\PdfContentDto;
use SignerPHP\Application\DTO\SigningContextDto;
use SignerPHP\Application\DTO\SigningOptionsDto;
use SignerPHP\Application\DTO\SignPdfRequestDto;
use SignerPHP\Domain\Exception\SignProcessException;
use SignerPHP\Domain\ValueObject\VerifiedCertificate;
use SignerPHP\Infrastructure\Native\Contract\PdfDocumentPreparerInterface;
use SignerPHP\Infrastructure\Native\Contract\SignatureFactoryInterface;
use SignerPHP\Infrastructure\Native\Contract\SignedBufferBuilderInterface;
use SignerPHP\Infrastructure\Native\NativePdfSigningEngine;
use SignerPHP\Infrastructure\PdfCore\Buffer;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\Signature;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NativePdfSigningEngineTest extends TestCase
{
    public function test_sign_orchestrates_preparer_factory_and_builder(): void
    {
        $preparer = new class implements PdfDocumentPreparerInterface
        {
            public ?string $receivedContent = null;

            public function prepare(string $pdfContent): PdfDocument
            {
                $this->receivedContent = $pdfContent;
                $document = new PdfDocument;
                $document->setBufferFromString($pdfContent);

                return $document;
            }
        };

        $factory = new class implements SignatureFactoryInterface
        {
            public ?PdfDocument $receivedDocument = null;

            public function create(SigningContextDto $context, PdfDocument $pdfDocument): Signature
            {
                $this->receivedDocument = $pdfDocument;

                return Signature::new();
            }
        };

        $builder = new class implements SignedBufferBuilderInterface
        {
            public ?PdfDocument $receivedDocument = null;

            public ?Signature $receivedSignature = null;

            public ?SigningContextDto $receivedContext = null;

            public function build(PdfDocument $pdfDocument, Signature $signatureHandler, SigningContextDto $context): Buffer
            {
                $this->receivedDocument = $pdfDocument;
                $this->receivedSignature = $signatureHandler;
                $this->receivedContext = $context;

                return new Buffer('signed-content');
            }
        };

        $engine = new NativePdfSigningEngine($preparer, $factory, $builder);
        $result = $engine->sign($this->buildContext('input-pdf'));

        self::assertSame('signed-content', $result);
        self::assertSame('input-pdf', $preparer->receivedContent);
        self::assertSame('input-pdf', $factory->receivedDocument?->getBuffer()->raw());
        self::assertSame($factory->receivedDocument, $builder->receivedDocument);
        self::assertInstanceOf(Signature::class, $builder->receivedSignature);
        self::assertInstanceOf(SigningContextDto::class, $builder->receivedContext);
    }

    public function test_sign_wraps_errors_from_native_flow(): void
    {
        $preparer = new class implements PdfDocumentPreparerInterface
        {
            public function prepare(string $pdfContent): PdfDocument
            {
                throw new RuntimeException('boom');
            }
        };

        $engine = new NativePdfSigningEngine(
            $preparer,
            new class implements SignatureFactoryInterface
            {
                public function create(SigningContextDto $context, PdfDocument $pdfDocument): Signature
                {
                    return Signature::new();
                }
            },
            new class implements SignedBufferBuilderInterface
            {
                public function build(PdfDocument $pdfDocument, Signature $signatureHandler, SigningContextDto $context): Buffer
                {
                    return new Buffer('never-called');
                }
            }
        );

        $this->expectException(SignProcessException::class);
        $this->expectExceptionMessage('Root cause: boom');
        $engine->sign($this->buildContext('invalid-pdf-content'));
    }

    private function buildContext(string $pdfContent): SigningContextDto
    {
        $request = new SignPdfRequestDto(
            new PdfContentDto($pdfContent),
            new CertificateCredentialsDto('/tmp/not-used.pfx', 'pwd'),
            SigningOptionsDto::empty(),
        );

        return new SigningContextDto(
            $request,
            new VerifiedCertificate($request->certificate, ['validTo_time_t' => PHP_INT_MAX], ['cert' => 'c', 'pkey' => 'p', 'extracerts' => '']),
        );
    }
}

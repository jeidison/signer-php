<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native;

use PdfSigner\Application\Contract\PdfSigningEngineInterface;
use PdfSigner\Application\DTO\SigningContextDto;
use PdfSigner\Domain\Exception\SignProcessException;
use PdfSigner\Infrastructure\Native\Contract\PdfDocumentPreparerInterface;
use PdfSigner\Infrastructure\Native\Contract\SignatureFactoryInterface;
use PdfSigner\Infrastructure\Native\Contract\SignedBufferBuilderInterface;
use PdfSigner\Infrastructure\Native\Service\PdfDocumentPreparer;
use PdfSigner\Infrastructure\Native\Service\PdfSignatureFactory;
use PdfSigner\Infrastructure\Native\Service\Pkcs7Signer;
use PdfSigner\Infrastructure\Native\Service\SignedBufferBuilder;
use PdfSigner\Infrastructure\Native\Service\XrefContentResolver;

final readonly class NativePdfSigningEngine implements PdfSigningEngineInterface
{
    public function __construct(
        private PdfDocumentPreparerInterface $documentPreparer = new PdfDocumentPreparer,
        private SignatureFactoryInterface $signatureFactory = new PdfSignatureFactory,
        private SignedBufferBuilderInterface $signedBufferBuilder = new SignedBufferBuilder(
            new XrefContentResolver,
            new Pkcs7Signer,
        ),
    ) {}

    public function sign(SigningContextDto $context): string
    {
        try {
            $pdfDocument = $this->documentPreparer->prepare($context->request->pdf->content);
            $signature = $this->signatureFactory->create($context, $pdfDocument);

            return (string) $this->signedBufferBuilder->build($pdfDocument, $signature, $context);
        } catch (\Throwable $throwable) {
            throw new SignProcessException(
                sprintf('Could not sign PDF using native v1 engine. Root cause: %s', $throwable->getMessage()),
                previous: $throwable,
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native;

use SignerPHP\Application\Contract\PdfSigningEngineInterface;
use SignerPHP\Application\DTO\SigningContextDto;
use SignerPHP\Domain\Exception\SignProcessException;
use SignerPHP\Infrastructure\Native\Contract\PdfDocumentPreparerInterface;
use SignerPHP\Infrastructure\Native\Contract\SignatureFactoryInterface;
use SignerPHP\Infrastructure\Native\Contract\SignedBufferBuilderInterface;
use SignerPHP\Infrastructure\Native\Service\PdfDocumentPreparer;
use SignerPHP\Infrastructure\Native\Service\PdfSignatureFactory;
use SignerPHP\Infrastructure\Native\Service\Pkcs7Signer;
use SignerPHP\Infrastructure\Native\Service\SignedBufferBuilder;
use SignerPHP\Infrastructure\Native\Service\XrefContentResolver;

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

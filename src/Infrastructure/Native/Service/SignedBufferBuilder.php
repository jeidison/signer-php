<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Service;

use SignerPHP\Application\DTO\SignatureProfile;
use SignerPHP\Application\DTO\SigningContextDto;
use SignerPHP\Infrastructure\Native\Contract\DocumentTimestampApplierInterface;
use SignerPHP\Infrastructure\Native\Contract\LongTermValidationApplierInterface;
use SignerPHP\Infrastructure\Native\Contract\Pkcs7SignerInterface;
use SignerPHP\Infrastructure\Native\Contract\SignedBufferBuilderInterface;
use SignerPHP\Infrastructure\Native\Contract\XrefContentResolverInterface;
use SignerPHP\Infrastructure\PdfCore\Buffer;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueHexString;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use SignerPHP\Infrastructure\PdfCore\Signature;
use SignerPHP\Infrastructure\PdfCore\Xref\Xref;

final readonly class SignedBufferBuilder implements SignedBufferBuilderInterface
{
    public function __construct(
        private XrefContentResolverInterface $xrefContentResolver,
        private Pkcs7SignerInterface $pkcs7Signer,
        private DocumentTimestampApplierInterface $documentTimestampApplier = new DocumentTimestampApplier,
        private LongTermValidationApplierInterface $longTermValidationApplier = new DocumentLongTermValidationApplier,
    ) {}

    public function build(PdfDocument $pdfDocument, Signature $signatureHandler, SigningContextDto $context): Buffer
    {
        if (! $signatureHandler->hasCertificate()) {
            return $pdfDocument->getBuffer();
        }

        $pdfDocument->updateModifyDate();
        $signature = $signatureHandler->generateSignatureInDocument();

        [$docToXref, $objectOffsets] = Xref::new()
            ->withPdfDocument($pdfDocument)
            ->generateContentToXref();

        $xrefOffset = $docToXref->size();
        $objectOffsets[$signature->getOid()] = $docToXref->size();
        $xrefOffset += strlen($signature->toPdfEntry());

        $docFromXref = $this->xrefContentResolver->resolve($pdfDocument, $objectOffsets, $xrefOffset);

        $signature->withSizes($docToXref->size(), $docFromXref->size());
        $signature['Contents'] = new PDFValueSimple('');

        $signableDocument = new Buffer($docToXref->raw().$signature->toPdfEntry().$docFromXref->raw());
        $signatureContents = $this->pkcs7Signer->sign($signatureHandler, $signableDocument);

        $signature['Contents'] = new PDFValueHexString($signatureContents);
        $docToXref->data($signature->toPdfEntry());

        $signedRaw = $docToXref->raw().$docFromXref->raw();
        $timestamp = $context->request->options->timestamp;
        if ($timestamp !== null) {
            $signedRaw = $this->documentTimestampApplier->apply($signedRaw, $timestamp);
        }

        if (in_array($context->request->options->signatureProfile, [SignatureProfile::PadesBaselineLT, SignatureProfile::PadesBaselineLTA], true)) {
            $signedRaw = $this->longTermValidationApplier->apply($signedRaw);
        }

        if ($context->request->options->signatureProfile === SignatureProfile::PadesBaselineLTA && $timestamp !== null) {
            $signedRaw = $this->documentTimestampApplier->apply($signedRaw, $timestamp);
        }

        return new Buffer($signedRaw);
    }
}

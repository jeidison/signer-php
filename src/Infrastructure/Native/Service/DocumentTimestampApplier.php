<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Service;

use SignerPHP\Application\DTO\TimestampOptionsDto;
use SignerPHP\Domain\Exception\SignProcessException;
use SignerPHP\Infrastructure\Native\Contract\DocumentTimestampApplierInterface;
use SignerPHP\Infrastructure\Native\Contract\TimestampTokenProviderInterface;
use SignerPHP\Infrastructure\PdfCore\Buffer;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueHexString;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use SignerPHP\Infrastructure\PdfCore\Service\DocumentTimestampObjectAssembler;
use SignerPHP\Infrastructure\PdfCore\Signature;
use SignerPHP\Infrastructure\PdfCore\Xref\Xref;

final readonly class DocumentTimestampApplier implements DocumentTimestampApplierInterface
{
    public function __construct(
        private PdfDocumentPreparer $documentPreparer = new PdfDocumentPreparer,
        private XrefContentResolver $xrefContentResolver = new XrefContentResolver,
        private DocumentTimestampObjectAssembler $timestampAssembler = new DocumentTimestampObjectAssembler,
        private TimestampTokenProviderInterface $timestampTokenProvider = new OpenSslRfc3161TimestampTokenProvider,
    ) {}

    public function apply(string $signedPdfContent, TimestampOptionsDto $options): string
    {
        $pdfDocument = $this->documentPreparer->prepare($signedPdfContent);
        $timestamp = $this->timestampAssembler->assemble($pdfDocument);

        [$docToXref, $objectOffsets] = Xref::new()
            ->withPdfDocument($pdfDocument)
            ->generateContentToXref();

        $xrefOffset = $docToXref->size();
        $objectOffsets[$timestamp->getOid()] = $docToXref->size();
        $xrefOffset += strlen($timestamp->toPdfEntry());

        $docFromXref = $this->xrefContentResolver->resolve($pdfDocument, $objectOffsets, $xrefOffset);

        $timestamp->withSizes($docToXref->size(), $docFromXref->size());
        $timestamp['Contents'] = new PDFValueSimple('');

        $signableDocument = new Buffer($docToXref->raw().$timestamp->toPdfEntry().$docFromXref->raw());
        $byteRange = $this->extractByteRangeValues((string) $timestamp['ByteRange']);
        $timestampHex = $this->timestampTokenProvider->requestTokenHex($signableDocument->raw(), $byteRange, $options);
        $timestampHex = $this->normalizeTimestampHexSize($timestampHex);

        $timestamp['Contents'] = new PDFValueHexString($timestampHex);
        $docToXref->data($timestamp->toPdfEntry());

        return (string) new Buffer($docToXref->raw().$docFromXref->raw());
    }

    private function normalizeTimestampHexSize(string $timestampHex): string
    {
        $timestampHex = strtoupper(trim($timestampHex));
        if ($timestampHex === '') {
            throw new SignProcessException('Empty RFC3161 timestamp token.');
        }

        if (! ctype_xdigit($timestampHex)) {
            throw new SignProcessException('RFC3161 timestamp token is not valid hex.');
        }

        $max = Signature::SIGNATURE_MAX_LENGTH;
        if (strlen($timestampHex) > $max) {
            throw new SignProcessException(
                sprintf('RFC3161 token exceeds reserved signature size (%d > %d).', strlen($timestampHex), $max)
            );
        }

        return str_pad($timestampHex, $max, '0');
    }

    /**
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function extractByteRangeValues(string $byteRange): array
    {
        if (preg_match('/\\[\\s*(\\d+)\\s+(\\d+)\\s+(\\d+)\\s+(\\d+)\\s*\\]/', $byteRange, $matches) !== 1) {
            throw new SignProcessException('Could not parse ByteRange for document timestamp.');
        }

        return [(int) $matches[1], (int) $matches[2], (int) $matches[3], (int) $matches[4]];
    }
}

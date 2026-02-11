<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Service;

use PdfSigner\Infrastructure\Native\Contract\LongTermValidationApplierInterface;
use PdfSigner\Infrastructure\Native\Contract\PdfSignatureExtractorInterface;
use PdfSigner\Infrastructure\Native\Contract\SignatureCertificateCollectorInterface;
use PdfSigner\Infrastructure\Native\Contract\SignatureRevocationEvidenceCollectorInterface;
use PdfSigner\Infrastructure\Native\ValueObject\ExtractedPdfSignature;
use PdfSigner\Infrastructure\PdfCore\Buffer;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueList;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueReference;
use PdfSigner\Infrastructure\PdfCore\Service\TrailerObjectResolver;
use PdfSigner\Infrastructure\PdfCore\Xref\Xref;

final readonly class DocumentLongTermValidationApplier implements LongTermValidationApplierInterface
{
    public function __construct(
        private PdfDocumentPreparer $documentPreparer = new PdfDocumentPreparer,
        private XrefContentResolver $xrefContentResolver = new XrefContentResolver,
        private TrailerObjectResolver $trailerObjectResolver = new TrailerObjectResolver,
        private PdfSignatureExtractorInterface $signatureExtractor = new PdfSignatureExtractor,
        private SignatureCertificateCollectorInterface $certificateCollector = new OpenSslCmsCertificateCollector,
        private SignatureRevocationEvidenceCollectorInterface $revocationCollector = new OpenSslRevocationEvidenceCollector,
    ) {}

    public function apply(string $signedPdfContent): string
    {
        $signatures = $this->signatureExtractor->extract($signedPdfContent);
        if ($signatures === []) {
            return $signedPdfContent;
        }

        $pdfDocument = $this->documentPreparer->prepare($signedPdfContent);
        $rootObject = $this->trailerObjectResolver->resolveRootObject($pdfDocument);

        $certRefMap = [];
        $ocspRefMap = [];
        $crlRefMap = [];
        $vriEntries = [];

        foreach ($signatures as $signature) {
            $signatureChain = $this->certificateCollector->collectDerCertificates($signature->signatureHex);
            if ($signatureChain === []) {
                continue;
            }

            $revocationsByCert = $this->revocationCollector->collect($signatureChain);

            $signatureCertRefs = [];
            $signatureOcspRefs = [];
            $signatureCrlRefs = [];

            foreach ($signatureChain as $certIndex => $certDer) {
                $certHash = hash('sha256', $certDer);
                if (! isset($certRefMap[$certHash])) {
                    $certObject = $pdfDocument->createObject([
                        'Type' => '/EmbeddedFile',
                        // Name object must escape "/" inside MIME subtype.
                        'Subtype' => '/application#2Fpkix-cert',
                    ]);
                    $certObject->setStream($certDer, false);
                    $certRefMap[$certHash] = new PDFValueReference($certObject->getOid());
                }
                $signatureCertRefs[] = $certRefMap[$certHash];

                $revocation = $revocationsByCert[$certIndex] ?? ['ocsp' => [], 'crl' => []];
                foreach ($revocation['ocsp'] as $ocspDer) {
                    $ocspHash = hash('sha256', $ocspDer);
                    if (! isset($ocspRefMap[$ocspHash])) {
                        $ocspObject = $pdfDocument->createObject([
                            'Type' => '/EmbeddedFile',
                            // Name object must escape "/" inside MIME subtype.
                            'Subtype' => '/application#2Focsp-response',
                        ]);
                        $ocspObject->setStream($ocspDer, false);
                        $ocspRefMap[$ocspHash] = new PDFValueReference($ocspObject->getOid());
                    }
                    $signatureOcspRefs[] = $ocspRefMap[$ocspHash];
                }

                foreach ($revocation['crl'] as $crlDer) {
                    $crlHash = hash('sha256', $crlDer);
                    if (! isset($crlRefMap[$crlHash])) {
                        $crlObject = $pdfDocument->createObject([
                            'Type' => '/EmbeddedFile',
                            // Name object must escape "/" inside MIME subtype.
                            'Subtype' => '/application#2Fpkix-crl',
                        ]);
                        $crlObject->setStream($crlDer, false);
                        $crlRefMap[$crlHash] = new PDFValueReference($crlObject->getOid());
                    }
                    $signatureCrlRefs[] = $crlRefMap[$crlHash];
                }
            }

            $vriEntries[$this->signatureVriKey($signature)] = new PDFValueObject([
                'Cert' => new PDFValueList($this->dedupeRefs($signatureCertRefs)),
                'OCSP' => new PDFValueList($this->dedupeRefs($signatureOcspRefs)),
                'CRL' => new PDFValueList($this->dedupeRefs($signatureCrlRefs)),
            ]);
        }

        if ($certRefMap === []) {
            return $signedPdfContent;
        }

        $dssObject = $pdfDocument->createObject([
            'Type' => '/DSS',
            'Certs' => new PDFValueList(array_values($certRefMap)),
            'OCSPs' => new PDFValueList(array_values($ocspRefMap)),
            'CRLs' => new PDFValueList(array_values($crlRefMap)),
            'VRI' => new PDFValueObject($vriEntries),
        ]);

        $rootObject['DSS'] = new PDFValueReference($dssObject->getOid());
        $pdfDocument->addObject($rootObject);

        [$docToXref, $objectOffsets] = Xref::new()
            ->withPdfDocument($pdfDocument)
            ->generateContentToXref();

        $xrefOffset = $docToXref->size();
        $docFromXref = $this->xrefContentResolver->resolve($pdfDocument, $objectOffsets, $xrefOffset);

        return (string) new Buffer($docToXref->raw().$docFromXref->raw());
    }

    /**
     * @param  array<int, PDFValueReference>  $refs
     * @return array<int, PDFValueReference>
     */
    private function dedupeRefs(array $refs): array
    {
        $map = [];
        foreach ($refs as $ref) {
            $oid = $ref->asObjectReferenceOrNull();
            if ($oid === null || is_array($oid)) {
                continue;
            }

            $map[$oid] = $ref;
        }

        return array_values($map);
    }

    private function signatureVriKey(ExtractedPdfSignature $signature): string
    {
        $hex = strtoupper(trim($signature->signatureHex));
        $hex = preg_replace('/(?:00)+$/', '', $hex) ?? $hex;
        if ($hex === '') {
            return 'SIG'.strtoupper(hash('sha1', (string) $signature->index));
        }

        return strtoupper(hash('sha1', $hex));
    }
}

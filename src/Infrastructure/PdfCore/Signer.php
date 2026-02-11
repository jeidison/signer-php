<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore;

use DateTime;
use PdfSigner\Infrastructure\PdfCore\Exception\PdfCoreSigningException;
use PdfSigner\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueHexString;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use PdfSigner\Infrastructure\PdfCore\Xref\Xref;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 **/
class Signer
{
    private Signature $signature;

    private ?PdfDocument $pdfDocument = null;

    public function __construct()
    {
        $this->signature = Signature::new();
    }

    public static function new(): static
    {
        return new static;
    }

    public function withContent(string $pdfContent): self
    {
        $pdfDocument = new PdfDocument;
        $pdfDocument->setBufferFromString($pdfContent);

        return $this->withPdfDocument($pdfDocument);
    }

    private function withPdfDocument(PdfDocument $pdfDocument): self
    {
        $this->pdfDocument = $pdfDocument;

        return $this;
    }

    public function withMetadata(Metadata $metadata): self
    {
        $this->signature->withMetadata($metadata);

        return $this;
    }

    public function withSignatureAppearance(SignatureAppearance $appearance): self
    {
        $this->signature->withAppearance($appearance);

        return $this;
    }

    private function prepareDocumentToSign(): void
    {
        $pdfDocument = $this->requirePdfDocument();

        $structure = Struct::new()
            ->withPdfDocument($this->pdfDocument)
            ->parse();

        if ($structure->trailer === null) {
            throw new PdfCoreStructureException('Invalid PDF structure: missing trailer.');
        }

        $pdfDocument->setPdfVersion($structure->version);
        $pdfDocument->setTrailerObject($structure->trailer);
        $pdfDocument->setXrefPosition($structure->xrefPosition);
        $pdfDocument->setXrefTable($structure->xrefTable);
        $pdfDocument->setXrefTableVersion($structure->xrefVersion);
        $pdfDocument->setRevisions($structure->revisions);

        $oids = array_keys($structure->xrefTable);
        sort($oids);
        $lastOid = array_pop($oids);
        $pdfDocument->setMaxOid(is_int($lastOid) ? $lastOid : 0);
        $pdfDocument->acquirePagesInfo();

        $this->signature->withPdfDocument($pdfDocument);
    }

    public function withCertificate(string $pathCertificate, string $password): self
    {
        if (! is_file($pathCertificate) || ! is_readable($pathCertificate)) {
            throw new PdfCoreSigningException('Could not read file '.$pathCertificate);
        }

        $certFileContent = file_get_contents($pathCertificate);
        if ($certFileContent === false) {
            throw new PdfCoreSigningException('Could not read file '.$pathCertificate);
        }

        $certificate = [];
        if (! openssl_pkcs12_read($certFileContent, $certificate, $password)) {
            throw new PdfCoreSigningException('Could not get the certificates from file '.openssl_error_string());
        }

        if (! isset($certificate['cert']) || ! is_string($certificate['cert']) || trim($certificate['cert']) === '') {
            throw new PdfCoreSigningException('Could not get the public certificate from PKCS12 bundle.');
        }

        if (! isset($certificate['pkey']) || ! is_string($certificate['pkey']) || trim($certificate['pkey']) === '') {
            throw new PdfCoreSigningException('Could not get the private key from PKCS12 bundle.');
        }

        if (! isset($certificate['extracerts']) || ! is_string($certificate['extracerts'])) {
            $certificate['extracerts'] = '';
        }

        $certInfo = openssl_x509_parse($certificate['cert']);
        if (! is_array($certInfo) || ! isset($certInfo['validTo_time_t']) || ! is_int($certInfo['validTo_time_t'])) {
            throw new PdfCoreSigningException('Could not parse X509 certificate metadata.');
        }

        $expirationDate = $certInfo['validTo_time_t'];

        if ($expirationDate < time()) {
            throw new PdfCoreSigningException('Certificate has expired.');
        }

        $this->signature->withCertificate($certificate);

        return $this;
    }

    public function sign(): string
    {
        $this->prepareDocumentToSign();

        return (string) $this->toBuffer();
    }

    private function toBuffer(): Buffer
    {
        $pdfDocument = $this->requirePdfDocument();

        if (! $this->signature->hasCertificate()) {
            return $pdfDocument->getBuffer();
        }

        $pdfDocument->updateModifyDate();
        $signature = $this->signature->generateSignatureInDocument();

        [$docToXref, $objOffSets] = Xref::new()
            ->withPdfDocument($pdfDocument)
            ->generateContentToXref();

        $xrefOffset = $docToXref->size();

        $objOffSets[$signature->getOid()] = $docToXref->size();
        $xrefOffset += strlen($signature->toPdfEntry());

        $docVersionString = str_replace('PDF-', '', $pdfDocument->getPdfVersion());

        $targetVersion = $pdfDocument->getXrefTableVersion();
        if ($pdfDocument->getXrefTableVersion() >= '1.5') {
            if ($docVersionString > $targetVersion) {
                $targetVersion = $docVersionString;
            }
        } elseif ($docVersionString < $targetVersion) {
            $targetVersion = $docVersionString;
        }

        if ($targetVersion >= '1.5') {
            $trailer = $pdfDocument->createObject(clone $pdfDocument->getTrailerObject());

            $objOffSets[$trailer->getOid()] = $xrefOffset;

            $xref = Xref::new()->buildXref15($objOffSets);

            $trailer['Index'] = explode(' ', (string) $xref['Index']);
            $trailer['W'] = $xref['W'];
            $trailer['Size'] = $pdfDocument->getMaxOid() + 1;
            $trailer['Type'] = '/XRef';

            $ID2 = md5(''.(new DateTime)->getTimestamp().'-'.$pdfDocument->getXrefPosition().$pdfDocument->getTrailerObject());
            $currentId = $trailer['ID'][0] ?? new PDFValueHexString(strtoupper(md5((string) $pdfDocument->getTrailerObject())));
            $trailer['ID'] = [$currentId, new PDFValueHexString(strtoupper($ID2))];

            if (isset($trailer['DecodeParms'])) {
                unset($trailer['DecodeParms']);
            }

            if (isset($trailer['Filter'])) {
                unset($trailer['Filter']);
            }

            $trailer->setStream($xref['stream'], false);
            $trailer['Prev'] = $pdfDocument->getXrefPosition();

            $docFromXref = new Buffer($trailer->toPdfEntry());
            $docFromXref->data('startxref'.PHP_EOL.$xrefOffset.PHP_EOL.'%%EOF'.PHP_EOL);
        } else {
            $xrefContent = Xref::new()->buildXref($objOffSets);

            $pdfDocument->getTrailerObject()['Size'] = $pdfDocument->getMaxOid() + 1;
            $pdfDocument->getTrailerObject()['Prev'] = $pdfDocument->getXrefPosition();

            $docFromXref = new Buffer($xrefContent);
            $docFromXref->data("trailer\n".$pdfDocument->getTrailerObject());
            $docFromXref->data("\nstartxref\n{$xrefOffset}\n%%EOF\n");
        }

        $signature->withSizes($docToXref->size(), $docFromXref->size());
        $signature['Contents'] = new PDFValueSimple('');

        $signableDocument = new Buffer($docToXref->raw().$signature->toPdfEntry().$docFromXref->raw());

        $tmpFolder = sys_get_temp_dir();
        $tempFilename = tempnam($tmpFolder, 'pdfsign');
        if ($tempFilename === false) {
            throw new PdfCoreSigningException('Could not allocate temporary file to sign PDF.');
        }

        $tempFile = fopen($tempFilename, 'wb');
        if ($tempFile === false) {
            throw new PdfCoreSigningException('Could not open temporary file to sign PDF.');
        }

        try {
            if (fwrite($tempFile, $signableDocument->raw()) === false) {
                throw new PdfCoreSigningException('Could not write signable PDF content to temporary file.');
            }
        } finally {
            fclose($tempFile);
        }

        try {
            $signatureContents = $this->signature->calculatePkcs7Signature($tempFilename, $tmpFolder);
        } finally {
            if (is_file($tempFilename)) {
                unlink($tempFilename);
            }
        }

        $signature['Contents'] = new PDFValueHexString($signatureContents);

        $docToXref->data($signature->toPdfEntry());

        return new Buffer($docToXref->raw().$docFromXref->raw());
    }

    private function requirePdfDocument(): PdfDocument
    {
        if ($this->pdfDocument === null) {
            throw new PdfCoreSigningException('PDF content is required before signing.');
        }

        return $this->pdfDocument;
    }
}

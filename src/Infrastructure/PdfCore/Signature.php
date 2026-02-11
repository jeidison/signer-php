<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore;

use SignerPHP\Application\DTO\CertificationLevel;
use SignerPHP\Infrastructure\PdfCore\Contract\SignatureRuntimeInterface;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreSigningException;
use SignerPHP\Infrastructure\PdfCore\Service\NativeSignatureRuntime;
use SignerPHP\Infrastructure\PdfCore\Service\SignatureObjectAssembler;

class Signature
{
    const SIGNATURE_MAX_LENGTH = 11742;

    private array $certificate = [
        'cert' => '',
        'pkey' => '',
        'extracerts' => '',
    ];

    private Metadata $metadata;

    private SignatureAppearance $appearance;

    private PdfDocument $pdfDocument;

    private ?SignatureObjectAssembler $signatureObjectAssembler = null;

    private string $subFilter = SignatureObject::SUBFILTER_PKCS7_DETACHED;

    private ?CertificationLevel $certificationLevel = null;

    public function __construct(
        private readonly SignatureRuntimeInterface $runtime = new NativeSignatureRuntime,
    ) {
        $this->appearance = SignatureAppearance::new();
    }

    public static function new(?SignatureRuntimeInterface $runtime = null): self
    {
        return new self($runtime ?? new NativeSignatureRuntime);
    }

    public function withCertificate(array $certificate): self
    {
        $this->certificate = $certificate;

        return $this;
    }

    public function withAppearance(SignatureAppearance $appearance): self
    {
        $this->appearance = $appearance;

        return $this;
    }

    public function withoutAppearance(): self
    {
        $this->appearance->withImage(null);

        return $this;
    }

    public function withPdfDocument(PdfDocument $pdfDocument): self
    {
        $this->pdfDocument = $pdfDocument;

        return $this;
    }

    public function withMetadata(Metadata $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function hasCertificate(): bool
    {
        return ! empty($this->certificate['cert']);
    }

    public function withSubFilter(string $subFilter): self
    {
        $this->subFilter = $subFilter;

        return $this;
    }

    public function withCertificationLevel(?CertificationLevel $level): self
    {
        $this->certificationLevel = $level;

        return $this;
    }

    public function generateSignatureInDocument(): SignatureObject
    {
        $signatureObject = $this->signatureObjectAssembler()
            ->assemble(
                $this->requirePdfDocument(),
                $this->appearance,
                $this->requireMetadata(),
                $this->certificationLevel,
            );

        return $signatureObject->withSubFilter($this->subFilter);
    }

    public function calculatePkcs7Signature(string $fileNameToSign, string $tmpFolder = '/tmp'): string
    {
        $filesizeOriginal = $this->runtime->fileSize($fileNameToSign);
        if ($filesizeOriginal === false) {
            throw new PdfCoreSigningException('Could not open file '.$fileNameToSign);
        }

        $tempFilename = $this->runtime->createTempFile($tmpFolder, 'pdfsign');
        if ($tempFilename === false) {
            throw new PdfCoreSigningException('Could not create a temporary filename');
        }

        try {
            if (! $this->runtime->signPkcs7($fileNameToSign, $tempFilename, $this->certificate['cert'], $this->certificate['pkey'])) {
                throw new PdfCoreSigningException('Failed to sign file '.$fileNameToSign);
            }

            $signature = $this->runtime->readFile($tempFilename);
            if ($signature === false) {
                throw new PdfCoreSigningException('Could not read generated signature file.');
            }

            $signature = substr($signature, $filesizeOriginal);
            $separatorPosition = strpos($signature, "%%EOF\n\n------");
            if ($separatorPosition === false) {
                throw new PdfCoreSigningException('Could not extract PKCS7 payload from signed output.');
            }

            $signature = substr($signature, $separatorPosition + 13);
            $tmpArr = explode("\n\n", $signature);
            if (! isset($tmpArr[1])) {
                throw new PdfCoreSigningException('Malformed PKCS7 output.');
            }

            $decoded = $this->runtime->decodeBase64(trim($tmpArr[1]));
            if ($decoded === false) {
                throw new PdfCoreSigningException('Could not decode PKCS7 base64 payload.');
            }

            $hex = $this->runtime->toHex($decoded);

            return str_pad($hex, self::SIGNATURE_MAX_LENGTH, '0');
        } finally {
            if ($this->runtime->isFile($tempFilename)) {
                $this->runtime->removeFile($tempFilename);
            }
        }
    }

    private function requirePdfDocument(): PdfDocument
    {
        if (! isset($this->pdfDocument)) {
            throw new PdfCoreSigningException('PDF document is required to generate the signature.');
        }

        return $this->pdfDocument;
    }

    private function requireMetadata(): Metadata
    {
        if (! isset($this->metadata)) {
            throw new PdfCoreSigningException('Metadata is required to generate the signature.');
        }

        return $this->metadata;
    }

    private function signatureObjectAssembler(): SignatureObjectAssembler
    {
        $this->signatureObjectAssembler ??= new SignatureObjectAssembler;

        return $this->signatureObjectAssembler;
    }
}

<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Service;

use PdfSigner\Domain\Exception\SignatureValidationException;
use PdfSigner\Infrastructure\Native\Contract\ProcessRunnerInterface;
use PdfSigner\Infrastructure\Native\Contract\SignatureCryptoVerifierInterface;
use PdfSigner\Infrastructure\Native\ValueObject\SignatureCryptoVerification;

final class OpenSslSignatureCryptoVerifier implements SignatureCryptoVerifierInterface
{
    public function __construct(
        private readonly ProcessRunnerInterface $processRunner = new ShellProcessRunner,
    ) {}

    public function verify(string $signedContent, string $signatureHex): SignatureCryptoVerification
    {
        if ($signatureHex === '' || preg_match('/\A[0]+\z/', $signatureHex) === 1) {
            return new SignatureCryptoVerification(false, 'Empty signature contents.');
        }

        $signatureDer = hex2bin($signatureHex);
        if ($signatureDer === false) {
            return new SignatureCryptoVerification(false, 'Invalid signature hex payload.');
        }

        $tmpDir = sys_get_temp_dir();
        $contentFile = $this->createTempFile($tmpDir, 'pdf-sig-content');
        $signatureFile = $this->createTempFile($tmpDir, 'pdf-sig-cms');
        $outputFile = $this->createTempFile($tmpDir, 'pdf-sig-out');

        file_put_contents($contentFile, $signedContent);
        file_put_contents($signatureFile, $signatureDer);

        try {
            $command = sprintf(
                'openssl cms -verify -binary -inform DER -in %s -content %s -noverify -out %s',
                escapeshellarg($signatureFile),
                escapeshellarg($contentFile),
                escapeshellarg($outputFile),
            );

            $result = $this->processRunner->run($command);
            if (! $result->succeeded()) {
                return new SignatureCryptoVerification(false, $result->outputAsString());
            }

            return new SignatureCryptoVerification(true);
        } finally {
            @unlink($contentFile);
            @unlink($signatureFile);
            @unlink($outputFile);
        }
    }

    private function createTempFile(string $tmpDir, string $prefix): string
    {
        $path = tempnam($tmpDir, $prefix);
        if ($path === false) {
            throw new SignatureValidationException('Could not create temporary files for signature validation.');
        }

        return $path;
    }
}

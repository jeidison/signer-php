<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Service;

use PdfSigner\Domain\Exception\SignProcessException;
use PdfSigner\Infrastructure\Native\Contract\Pkcs7SignerInterface;
use PdfSigner\Infrastructure\PdfCore\Buffer;
use PdfSigner\Infrastructure\PdfCore\Signature;

final class Pkcs7Signer implements Pkcs7SignerInterface
{
    public function sign(Signature $signatureHandler, Buffer $signableDocument): string
    {
        $tmpFolder = sys_get_temp_dir();
        $tempFilename = tempnam($tmpFolder, 'pdfsign');
        if ($tempFilename === false) {
            throw new SignProcessException('Could not allocate temporary file to sign PDF.');
        }

        file_put_contents($tempFilename, $signableDocument->raw());

        try {
            return $signatureHandler->calculatePkcs7Signature($tempFilename, $tmpFolder);
        } finally {
            @unlink($tempFilename);
        }
    }
}

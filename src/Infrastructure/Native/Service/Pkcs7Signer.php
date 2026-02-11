<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Service;

use SignerPHP\Domain\Exception\SignProcessException;
use SignerPHP\Infrastructure\Native\Contract\Pkcs7SignerInterface;
use SignerPHP\Infrastructure\PdfCore\Buffer;
use SignerPHP\Infrastructure\PdfCore\Signature;

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

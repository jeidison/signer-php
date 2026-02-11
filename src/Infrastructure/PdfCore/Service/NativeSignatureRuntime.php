<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore\Service;

use PdfSigner\Infrastructure\PdfCore\Contract\SignatureRuntimeInterface;

final class NativeSignatureRuntime implements SignatureRuntimeInterface
{
    public function fileSize(string $path): int|false
    {
        return filesize($path);
    }

    public function createTempFile(string $directory, string $prefix): string|false
    {
        return tempnam($directory, $prefix);
    }

    public function signPkcs7(string $inputFile, string $outputFile, string $certificate, string $privateKey): bool
    {
        return openssl_pkcs7_sign($inputFile, $outputFile, $certificate, $privateKey, [], PKCS7_BINARY | PKCS7_DETACHED);
    }

    public function readFile(string $path): string|false
    {
        return file_get_contents($path);
    }

    public function removeFile(string $path): void
    {
        @unlink($path);
    }

    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    public function decodeBase64(string $value): string|false
    {
        return base64_decode($value, true);
    }

    public function toHex(string $binary): string
    {
        return bin2hex($binary);
    }
}

<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore\Contract;

interface SignatureRuntimeInterface
{
    public function fileSize(string $path): int|false;

    public function createTempFile(string $directory, string $prefix): string|false;

    public function signPkcs7(string $inputFile, string $outputFile, string $certificate, string $privateKey): bool;

    public function readFile(string $path): string|false;

    public function removeFile(string $path): void;

    public function isFile(string $path): bool;

    public function decodeBase64(string $value): string|false;

    public function toHex(string $binary): string;
}

<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\ValueObject;

final readonly class ExtractedPdfSignature
{
    /**
     * @param  array{0:int,1:int,2:int,3:int}  $byteRange
     */
    public function __construct(
        public int $index,
        public array $byteRange,
        public string $signatureHex,
        public string $signedContent,
        public bool $byteRangeValid,
        public ?string $byteRangeError = null,
    ) {}
}

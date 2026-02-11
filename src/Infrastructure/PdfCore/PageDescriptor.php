<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore;

final readonly class PageDescriptor
{
    /**
     * @param  array<int, mixed>  $size
     */
    public function __construct(
        public int $id,
        public array $size,
    ) {}
}

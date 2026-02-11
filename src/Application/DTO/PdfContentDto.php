<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final readonly class PdfContentDto
{
    public function __construct(public string $content) {}
}

<?php

declare(strict_types=1);

namespace SignerPHP\Presentation;

use SignerPHP\Application\DTO\PdfContentDto;
use SignerPHP\Application\DTO\ProtectionOptionsDto;
use SignerPHP\Application\DTO\ProtectPdfRequestDto;
use SignerPHP\Application\Service\PdfProtectionService;
use SignerPHP\Domain\Exception\SignerException;

final class PdfProtectionBuilder
{
    private ?PdfContentDto $content = null;

    private ?ProtectionOptionsDto $options = null;

    public function __construct(private readonly PdfProtectionService $service) {}

    public static function new(PdfProtectionService $service): self
    {
        return new self($service);
    }

    public function withPdfContent(string $content): self
    {
        $this->content = new PdfContentDto($content);

        return $this;
    }

    public function withProtection(ProtectionOptionsDto $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function protect(): string
    {
        if ($this->content === null) {
            throw new SignerException('PDF content is required. Use withPdfContent().');
        }

        if ($this->options === null) {
            throw new SignerException('Protection options are required. Use withProtection().');
        }

        return $this->service->protect(new ProtectPdfRequestDto($this->content, $this->options));
    }
}

<?php

declare(strict_types=1);

namespace PdfSigner\Presentation;

use PdfSigner\Application\DTO\BrazilPolicyLpaUrlsDto;
use PdfSigner\Application\DTO\BrazilTrustAnchorsOptionsDto;
use PdfSigner\Application\DTO\PdfContentDto;
use PdfSigner\Application\DTO\SignatureValidationOptionsDto;
use PdfSigner\Application\DTO\SignatureValidationResultDto;
use PdfSigner\Application\DTO\ValidatePdfRequestDto;
use PdfSigner\Application\Service\PdfSignatureValidationService;
use PdfSigner\Domain\Exception\PdfSignerException;

final class PdfSignatureValidatorBuilder
{
    private ?PdfContentDto $content = null;

    private SignatureValidationOptionsDto $options;

    public function __construct(private readonly PdfSignatureValidationService $service)
    {
        $this->options = new SignatureValidationOptionsDto;
    }

    public static function new(PdfSignatureValidationService $service): self
    {
        return new self($service);
    }

    public function withPdfContent(string $content): self
    {
        $this->content = new PdfContentDto($content);

        return $this;
    }

    public function enableTrustChainValidation(?string $trustStorePath = null): self
    {
        $this->options = new SignatureValidationOptionsDto(
            checkTrustChain: true,
            trustStorePath: $trustStorePath,
        );

        return $this;
    }

    public function withBrazilPolicy(
        ?string $trustStorePath = null,
        ?BrazilPolicyLpaUrlsDto $lpaUrls = null,
        ?BrazilTrustAnchorsOptionsDto $trustAnchors = null,
    ): self {
        $lpaUrls ??= new BrazilPolicyLpaUrlsDto;
        $trustAnchors ??= BrazilTrustAnchorsOptionsDto::defaults();

        $this->options = new SignatureValidationOptionsDto(
            checkTrustChain: true,
            trustStorePath: $trustStorePath,
            trustAnchorsDirectory: $trustAnchors->directory,
            trustAnchorsUrls: $trustAnchors->urls,
            policy: 'br-iti',
            checkPolicyList: true,
            lpaUrlAsn1Pades: $lpaUrls->lpaUrlAsn1Pades,
            lpaUrlAsn1SignaturePades: $lpaUrls->lpaUrlAsn1SignaturePades,
        );

        return $this;
    }

    public function disableTrustChainValidation(): self
    {
        $this->options = new SignatureValidationOptionsDto(
            checkTrustChain: false,
            trustStorePath: null,
            trustAnchorsDirectory: null,
            trustAnchorsUrls: null,
            policy: null,
            checkPolicyList: false,
            lpaUrlAsn1Pades: null,
            lpaUrlAsn1SignaturePades: null,
        );

        return $this;
    }

    public function validate(): SignatureValidationResultDto
    {
        if ($this->content === null) {
            throw new PdfSignerException('PDF content is required. Use withPdfContent().');
        }

        return $this->service->validate(new ValidatePdfRequestDto($this->content, $this->options));
    }
}

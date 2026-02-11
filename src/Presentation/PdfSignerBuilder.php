<?php

declare(strict_types=1);

namespace SignerPHP\Presentation;

use SignerPHP\Application\Contract\DefaultTimestampOptionsProviderInterface;
use SignerPHP\Application\DTO\BrazilSignaturePolicyOptionsDto;
use SignerPHP\Application\DTO\CertificateCredentialsDto;
use SignerPHP\Application\DTO\CertificationLevel;
use SignerPHP\Application\DTO\PdfContentDto;
use SignerPHP\Application\DTO\ProtectionOptionsDto;
use SignerPHP\Application\DTO\ProtectPdfRequestDto;
use SignerPHP\Application\DTO\SignatureAppearanceDto;
use SignerPHP\Application\DTO\SignatureMetadataDto;
use SignerPHP\Application\DTO\SignatureProfile;
use SignerPHP\Application\DTO\SigningOptionsDto;
use SignerPHP\Application\DTO\SignPdfRequestDto;
use SignerPHP\Application\DTO\TimestampOptionsDto;
use SignerPHP\Application\Service\PdfProtectionService;
use SignerPHP\Application\Service\PdfSigningService;
use SignerPHP\Domain\Exception\PdfSignerException;
use SignerPHP\Infrastructure\Native\Service\DefaultTimestampOptionsProvider;

final class PdfSignerBuilder
{
    private ?PdfContentDto $content = null;

    private ?CertificateCredentialsDto $certificate = null;

    private ?SignatureMetadataDto $metadata = null;

    private ?SignatureAppearanceDto $appearance = null;

    private ?TimestampOptionsDto $timestamp = null;

    private ?TimestampOptionsDto $defaultTimestamp = null;

    private ?ProtectionOptionsDto $protection = null;

    private bool $useDefaultAppearance = true;

    private bool $useDefaultTimestamp = false;

    private SignatureProfile $signatureProfile = SignatureProfile::PdfBasic;

    private ?CertificationLevel $certificationLevel = null;

    public function __construct(
        private readonly PdfSigningService $service,
        private readonly ?PdfProtectionService $protectionService = null,
        private readonly DefaultTimestampOptionsProviderInterface $defaultTimestampProvider = new DefaultTimestampOptionsProvider,
    ) {}

    public static function new(
        PdfSigningService $service,
        ?PdfProtectionService $protectionService = null,
        ?DefaultTimestampOptionsProviderInterface $defaultTimestampProvider = null
    ): self {
        return new self($service, $protectionService, $defaultTimestampProvider ?? new DefaultTimestampOptionsProvider);
    }

    public function withPdfContent(string $content): self
    {
        $this->content = new PdfContentDto($content);

        return $this;
    }

    public function withCertificatePath(string $path, string $password): self
    {
        $this->certificate = CertificateCredentialsDto::fromPath($path, $password);

        return $this;
    }

    public function withCertificateContent(string $content, string $password): self
    {
        $this->certificate = CertificateCredentialsDto::fromContent($content, $password);

        return $this;
    }

    public function withMetadata(SignatureMetadataDto $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function withAppearance(SignatureAppearanceDto $appearance): self
    {
        $this->appearance = $appearance;

        return $this;
    }

    public function withoutDefaultAppearance(): self
    {
        $this->useDefaultAppearance = false;

        return $this;
    }

    public function withTimestamp(?TimestampOptionsDto $timestamp = null): self
    {
        $this->timestamp = $timestamp;
        $this->useDefaultTimestamp = true;

        return $this;
    }

    public function withDefaultTimestampProfile(TimestampOptionsDto $timestamp): self
    {
        $this->defaultTimestamp = $timestamp;
        $this->useDefaultTimestamp = true;

        return $this;
    }

    public function withoutTimestamp(): self
    {
        $this->timestamp = null;
        $this->useDefaultTimestamp = false;

        return $this;
    }

    public function withProtection(ProtectionOptionsDto $protection): self
    {
        $this->protection = $protection;

        return $this;
    }

    public function withPadesBaselineB(): self
    {
        $this->signatureProfile = SignatureProfile::PadesBaselineB;

        return $this;
    }

    public function withPadesBaselineT(): self
    {
        $this->signatureProfile = SignatureProfile::PadesBaselineT;

        return $this;
    }

    public function withPadesBaselineLT(): self
    {
        $this->signatureProfile = SignatureProfile::PadesBaselineLT;

        return $this;
    }

    public function withPadesBaselineLTA(): self
    {
        $this->signatureProfile = SignatureProfile::PadesBaselineLTA;

        return $this;
    }

    public function withCertificationLevel(CertificationLevel|int $level): self
    {
        $resolved = is_int($level) ? CertificationLevel::fromInt($level) : $level;
        if ($resolved === null) {
            throw new PdfSignerException('Certification level must be one of: 1, 2 or 3.');
        }

        $this->certificationLevel = $resolved;

        return $this;
    }

    public function withBrazilPolicy(BrazilSignaturePolicyOptionsDto $policy): self
    {
        $this->signatureProfile = SignatureProfile::PadesBaselineLTA;
        $this->certificationLevel = CertificationLevel::FormFillAndSignatures;
        $this->timestamp = $policy->toTimestampOptions();

        return $this;
    }

    public function protectThenSign(): string
    {
        if ($this->protection === null) {
            throw new PdfSignerException('Protection options are required. Use withProtection().');
        }

        return $this->sign();
    }

    public function sign(): string
    {
        if ($this->content === null) {
            throw new PdfSignerException('PDF content is required. Use withPdfContent().');
        }

        if ($this->certificate === null) {
            throw new PdfSignerException('Certificate is required. Use withCertificatePath() or withCertificateContent().');
        }

        $pdfContent = $this->content->content;
        if ($this->protection !== null) {
            if ($this->protectionService === null) {
                throw new PdfSignerException('Protection service is not available in this builder.');
            }

            $pdfContent = $this->protectionService->protect(
                new ProtectPdfRequestDto(
                    new PdfContentDto($pdfContent),
                    $this->protection,
                )
            );
        }

        $resolvedTimestamp = $this->resolveTimestamp();
        if (
            in_array($this->signatureProfile, [
                SignatureProfile::PadesBaselineT,
                SignatureProfile::PadesBaselineLT,
                SignatureProfile::PadesBaselineLTA,
            ], true)
            && $resolvedTimestamp === null
        ) {
            throw new PdfSignerException(
                sprintf(
                    '%s requires timestamp. Use withTimestamp().',
                    match ($this->signatureProfile) {
                        SignatureProfile::PadesBaselineLT => 'PAdES Baseline-LT',
                        SignatureProfile::PadesBaselineLTA => 'PAdES Baseline-LTA',
                        default => 'PAdES Baseline-T',
                    }
                )
            );
        }

        $request = new SignPdfRequestDto(
            new PdfContentDto($pdfContent),
            $this->certificate,
            new SigningOptionsDto(
                $this->metadata,
                $this->appearance,
                $resolvedTimestamp,
                $this->useDefaultAppearance,
                $this->signatureProfile,
                $this->certificationLevel
            ),
        );

        return $this->service->sign($request);
    }

    private function resolveTimestamp(): ?TimestampOptionsDto
    {
        if ($this->timestamp !== null) {
            return $this->timestamp;
        }

        if (! $this->useDefaultTimestamp) {
            return null;
        }

        return $this->defaultTimestamp ?? $this->defaultTimestampProvider->makeDefault();
    }
}

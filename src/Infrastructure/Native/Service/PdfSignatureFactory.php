<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Service;

use SignerPHP\Application\DTO\SignatureAppearanceDto;
use SignerPHP\Application\DTO\SignatureProfile;
use SignerPHP\Application\DTO\SigningContextDto;
use SignerPHP\Infrastructure\Native\Contract\DefaultSignatureAppearanceProviderInterface;
use SignerPHP\Infrastructure\Native\Contract\SignatureFactoryInterface;
use SignerPHP\Infrastructure\PdfCore\Metadata;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\Signature;
use SignerPHP\Infrastructure\PdfCore\SignatureAppearance;
use SignerPHP\Infrastructure\PdfCore\SignatureObject;

final class PdfSignatureFactory implements SignatureFactoryInterface
{
    public function __construct(
        private readonly DefaultSignatureAppearanceProviderInterface $defaultAppearanceProvider = new DefaultSignatureAppearanceProvider,
    ) {}

    public function create(SigningContextDto $context, PdfDocument $pdfDocument): Signature
    {
        $signature = Signature::new()
            ->withPdfDocument($pdfDocument)
            ->withCertificate($context->verifiedCertificate->bundle)
            ->withMetadata($this->toMetadata($context))
            ->withSubFilter($this->resolveSubFilter($context))
            ->withCertificationLevel($context->request->options->certificationLevel);

        $appearance = $this->resolveAppearance($context);
        if ($appearance !== null) {
            $signature->withAppearance(
                SignatureAppearance::new()
                    ->withImage($appearance->imagePath)
                    ->withRect($appearance->normalizedRect())
                    ->addSignAppearanceInPage($appearance->page)
            );
        }

        return $signature;
    }

    private function resolveAppearance(SigningContextDto $context): ?SignatureAppearanceDto
    {
        $appearance = $context->request->options->appearance;
        if ($appearance !== null) {
            return $appearance;
        }

        if (! $context->request->options->useDefaultAppearance) {
            return null;
        }

        return $this->defaultAppearanceProvider->makeDefault();
    }

    private function toMetadata(SigningContextDto $context): Metadata
    {
        $metadata = $context->request->options->metadata;

        return Metadata::new()
            ->withName($metadata?->actor?->name)
            ->withReason($metadata?->reason)
            ->withLocation($metadata?->location)
            ->withContactInfo($metadata?->actor?->contactInfo);
    }

    private function resolveSubFilter(SigningContextDto $context): string
    {
        return match ($context->request->options->signatureProfile) {
            SignatureProfile::PadesBaselineB, SignatureProfile::PadesBaselineT, SignatureProfile::PadesBaselineLT, SignatureProfile::PadesBaselineLTA => SignatureObject::SUBFILTER_ETSI_CADES_DETACHED,
            default => SignatureObject::SUBFILTER_PKCS7_DETACHED,
        };
    }
}

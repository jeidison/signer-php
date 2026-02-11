<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Service;

use PdfSigner\Application\DTO\SignatureAppearanceDto;
use PdfSigner\Application\DTO\SignatureProfile;
use PdfSigner\Application\DTO\SigningContextDto;
use PdfSigner\Infrastructure\Native\Contract\DefaultSignatureAppearanceProviderInterface;
use PdfSigner\Infrastructure\Native\Contract\SignatureFactoryInterface;
use PdfSigner\Infrastructure\PdfCore\Metadata;
use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\Signature;
use PdfSigner\Infrastructure\PdfCore\SignatureAppearance;
use PdfSigner\Infrastructure\PdfCore\SignatureObject;

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

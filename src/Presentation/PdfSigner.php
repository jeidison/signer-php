<?php

declare(strict_types=1);

namespace PdfSigner\Presentation;

use PdfSigner\Application\Service\PdfProtectionService;
use PdfSigner\Application\Service\PdfSignatureValidationService;
use PdfSigner\Application\Service\PdfSigningService;
use PdfSigner\Infrastructure\Legacy\OpenSslCertificateValidator;
use PdfSigner\Infrastructure\Native\NativePdfProtectionEngine;
use PdfSigner\Infrastructure\Native\NativePdfSignatureValidationEngine;
use PdfSigner\Infrastructure\Native\NativePdfSigningEngine;

final class PdfSigner
{
    public static function signer(): PdfSignerBuilder
    {
        $signingService = new PdfSigningService(
            new OpenSslCertificateValidator,
            new NativePdfSigningEngine,
        );
        $protectionService = new PdfProtectionService(new NativePdfProtectionEngine);

        return PdfSignerBuilder::new($signingService, $protectionService);
    }

    public static function protection(): PdfProtectionBuilder
    {
        $service = new PdfProtectionService(new NativePdfProtectionEngine);

        return PdfProtectionBuilder::new($service);
    }

    public static function validation(): PdfSignatureValidatorBuilder
    {
        $service = new PdfSignatureValidationService(new NativePdfSignatureValidationEngine);

        return PdfSignatureValidatorBuilder::new($service);
    }
}

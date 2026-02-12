<?php

declare(strict_types=1);

namespace SignerPHP\Presentation;

use SignerPHP\Application\Service\PdfProtectionService;
use SignerPHP\Application\Service\PdfSignatureValidationService;
use SignerPHP\Application\Service\PdfSigningService;
use SignerPHP\Application\Service\TimestampService;
use SignerPHP\Infrastructure\Legacy\OpenSslCertificateValidator;
use SignerPHP\Infrastructure\Native\NativePdfProtectionEngine;
use SignerPHP\Infrastructure\Native\NativePdfSignatureValidationEngine;
use SignerPHP\Infrastructure\Native\NativePdfSigningEngine;

final class Signer
{
    public static function signer(): SignerBuilder
    {
        $signingService = new PdfSigningService(
            new OpenSslCertificateValidator,
            new NativePdfSigningEngine,
        );
        $protectionService = new PdfProtectionService(new NativePdfProtectionEngine);

        return SignerBuilder::new($signingService, $protectionService);
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

    public static function timestamp(): TimestampBuilder
    {
        return TimestampBuilder::new(new TimestampService);
    }
}

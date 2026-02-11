<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Application\Contract\PdfSignatureValidationEngineInterface;
use PdfSigner\Application\DTO\PdfContentDto;
use PdfSigner\Application\DTO\SignatureValidationResultDto;
use PdfSigner\Application\DTO\ValidatePdfRequestDto;
use PdfSigner\Application\Service\PdfSignatureValidationService;
use PHPUnit\Framework\TestCase;

final class PdfSignatureValidationServiceTest extends TestCase
{
    public function test_service_delegates_to_validation_engine(): void
    {
        $capture = new class
        {
            public ?string $content = null;
        };

        $engine = new class($capture) implements PdfSignatureValidationEngineInterface
        {
            public function __construct(private object $capture) {}

            public function validate(ValidatePdfRequestDto $request): SignatureValidationResultDto
            {
                $this->capture->content = $request->pdf->content;

                return new SignatureValidationResultDto(false, false, []);
            }
        };

        $service = new PdfSignatureValidationService($engine);
        $service->validate(new ValidatePdfRequestDto(new PdfContentDto('pdf-content')));

        self::assertSame('pdf-content', $capture->content);
    }
}

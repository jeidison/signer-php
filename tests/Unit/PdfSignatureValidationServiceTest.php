<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Application\Contract\PdfSignatureValidationEngineInterface;
use SignerPHP\Application\DTO\PdfContentDto;
use SignerPHP\Application\DTO\SignatureValidationResultDto;
use SignerPHP\Application\DTO\ValidatePdfRequestDto;
use SignerPHP\Application\Service\PdfSignatureValidationService;

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

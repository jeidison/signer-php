<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Application\Contract\PdfProtectionEngineInterface;
use SignerPHP\Application\DTO\ProtectionOptionsDto;
use SignerPHP\Application\DTO\ProtectPdfRequestDto;
use SignerPHP\Application\Service\PdfProtectionService;
use SignerPHP\Domain\Exception\PdfSignerException;
use SignerPHP\Presentation\PdfProtectionBuilder;

final class PdfProtectionBuilderTest extends TestCase
{
    public function test_builder_requires_pdf_content(): void
    {
        $builder = PdfProtectionBuilder::new($this->fakeService());

        $this->expectException(PdfSignerException::class);
        $builder->withProtection(new ProtectionOptionsDto(ownerPassword: 'owner'))->protect();
    }

    public function test_builder_requires_protection_options(): void
    {
        $builder = PdfProtectionBuilder::new($this->fakeService());

        $this->expectException(PdfSignerException::class);
        $builder->withPdfContent('pdf')->protect();
    }

    public function test_builder_protects_when_required_inputs_are_present(): void
    {
        $builder = PdfProtectionBuilder::new($this->fakeService('protected-ok'));

        $result = $builder
            ->withPdfContent('pdf')
            ->withProtection(new ProtectionOptionsDto(ownerPassword: 'owner'))
            ->protect();

        self::assertSame('protected-ok', $result);
    }

    public function test_builder_forwards_protection_options(): void
    {
        $capture = new class
        {
            public ?bool $allowCopy = null;
        };

        $engine = new class($capture) implements PdfProtectionEngineInterface
        {
            public function __construct(private object $capture) {}

            public function protect(ProtectPdfRequestDto $request): string
            {
                $this->capture->allowCopy = $request->options->allowCopy;

                return 'protected';
            }
        };

        $builder = PdfProtectionBuilder::new(new PdfProtectionService($engine));
        $builder
            ->withPdfContent('pdf')
            ->withProtection(ProtectionOptionsDto::preventCopy(ownerPassword: 'owner'))
            ->protect();

        self::assertFalse($capture->allowCopy);
    }

    private function fakeService(string $output = 'protected'): PdfProtectionService
    {
        $engine = new class($output) implements PdfProtectionEngineInterface
        {
            public function __construct(private readonly string $output) {}

            public function protect(ProtectPdfRequestDto $request): string
            {
                return $this->output;
            }
        };

        return new PdfProtectionService($engine);
    }
}

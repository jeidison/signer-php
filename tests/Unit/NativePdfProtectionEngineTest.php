<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Application\DTO\PdfContentDto;
use PdfSigner\Application\DTO\ProtectionOptionsDto;
use PdfSigner\Application\DTO\ProtectPdfRequestDto;
use PdfSigner\Domain\Exception\ProtectionProcessException;
use PdfSigner\Infrastructure\Native\Contract\PdfProtectionApplierInterface;
use PdfSigner\Infrastructure\Native\NativePdfProtectionEngine;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NativePdfProtectionEngineTest extends TestCase
{
    public function test_protect_delegates_to_protection_applier(): void
    {
        $capture = new class
        {
            public ?string $content = null;

            public ?ProtectionOptionsDto $options = null;
        };

        $applier = new class($capture) implements PdfProtectionApplierInterface
        {
            public function __construct(private object $capture) {}

            public function apply(string $pdfContent, ProtectionOptionsDto $options): string
            {
                $this->capture->content = $pdfContent;
                $this->capture->options = $options;

                return 'protected-content';
            }
        };

        $engine = new NativePdfProtectionEngine($applier);
        $request = new ProtectPdfRequestDto(
            new PdfContentDto('input-pdf'),
            ProtectionOptionsDto::preventCopy(ownerPassword: 'owner'),
        );

        $result = $engine->protect($request);

        self::assertSame('protected-content', $result);
        self::assertSame('input-pdf', $capture->content);
        self::assertInstanceOf(ProtectionOptionsDto::class, $capture->options);
        self::assertFalse($capture->options?->allowCopy);
    }

    public function test_protect_wraps_applier_errors(): void
    {
        $applier = new class implements PdfProtectionApplierInterface
        {
            public function apply(string $pdfContent, ProtectionOptionsDto $options): string
            {
                throw new RuntimeException('boom');
            }
        };

        $engine = new NativePdfProtectionEngine($applier);
        $request = new ProtectPdfRequestDto(
            new PdfContentDto('input-pdf'),
            new ProtectionOptionsDto(ownerPassword: 'owner'),
        );

        $this->expectException(ProtectionProcessException::class);
        $this->expectExceptionMessage('Root cause: boom');
        $engine->protect($request);
    }
}

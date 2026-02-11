<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Presentation\PdfProtectionBuilder;
use SignerPHP\Presentation\PdfSignatureValidatorBuilder;
use SignerPHP\Presentation\PdfSigner;
use SignerPHP\Presentation\PdfSignerBuilder;

final class PdfSignerFacadeTest extends TestCase
{
    public function test_facade_returns_builder(): void
    {
        $builder = PdfSigner::signer();

        self::assertInstanceOf(PdfSignerBuilder::class, $builder);
    }

    public function test_facade_returns_protection_builder(): void
    {
        $builder = PdfSigner::protection();

        self::assertInstanceOf(PdfProtectionBuilder::class, $builder);
    }

    public function test_facade_returns_signature_validator_builder(): void
    {
        $builder = PdfSigner::validation();

        self::assertInstanceOf(PdfSignatureValidatorBuilder::class, $builder);
    }
}

<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Presentation\PdfProtectionBuilder;
use PdfSigner\Presentation\PdfSignatureValidatorBuilder;
use PdfSigner\Presentation\PdfSigner;
use PdfSigner\Presentation\PdfSignerBuilder;
use PHPUnit\Framework\TestCase;

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

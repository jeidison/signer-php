<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Application\Contract\PdfSignatureValidationEngineInterface;
use PdfSigner\Application\DTO\BrazilPolicyLpaUrlsDto;
use PdfSigner\Application\DTO\BrazilTrustAnchorsOptionsDto;
use PdfSigner\Application\DTO\PdfContentDto;
use PdfSigner\Application\DTO\SignatureValidationResultDto;
use PdfSigner\Application\DTO\ValidatePdfRequestDto;
use PdfSigner\Application\Service\PdfSignatureValidationService;
use PdfSigner\Domain\Exception\PdfSignerException;
use PdfSigner\Presentation\PdfSignatureValidatorBuilder;
use PHPUnit\Framework\TestCase;

final class PdfSignatureValidatorBuilderTest extends TestCase
{
    public function test_builder_requires_pdf_content(): void
    {
        $builder = PdfSignatureValidatorBuilder::new($this->fakeService());

        $this->expectException(PdfSignerException::class);
        $builder->validate();
    }

    public function test_builder_validates_when_pdf_is_provided(): void
    {
        $builder = PdfSignatureValidatorBuilder::new($this->fakeService(new SignatureValidationResultDto(true, true, [])));
        $result = $builder->withPdfContent('pdf')->validate();

        self::assertTrue($result->hasSignatures);
        self::assertTrue($result->allValid);
    }

    public function test_builder_can_enable_trust_chain_validation_with_custom_store(): void
    {
        $captured = new class
        {
            public ?ValidatePdfRequestDto $request = null;
        };

        $engine = new class($captured) implements PdfSignatureValidationEngineInterface
        {
            public function __construct(private object $captured) {}

            public function validate(ValidatePdfRequestDto $request): SignatureValidationResultDto
            {
                $this->captured->request = $request;

                return new SignatureValidationResultDto(false, false, []);
            }
        };

        $builder = PdfSignatureValidatorBuilder::new(new PdfSignatureValidationService($engine));
        $builder
            ->withPdfContent('pdf')
            ->enableTrustChainValidation('/tmp/ca.pem')
            ->validate();

        self::assertInstanceOf(ValidatePdfRequestDto::class, $captured->request);
        self::assertInstanceOf(PdfContentDto::class, $captured->request->pdf);
        self::assertTrue($captured->request->options->checkTrustChain);
        self::assertSame('/tmp/ca.pem', $captured->request->options->trustStorePath);
    }

    public function test_builder_can_enable_brazil_policy_validation_preset(): void
    {
        $captured = new class
        {
            public ?ValidatePdfRequestDto $request = null;
        };

        $engine = new class($captured) implements PdfSignatureValidationEngineInterface
        {
            public function __construct(private object $captured) {}

            public function validate(ValidatePdfRequestDto $request): SignatureValidationResultDto
            {
                $this->captured->request = $request;

                return new SignatureValidationResultDto(false, false, []);
            }
        };

        $builder = PdfSignatureValidatorBuilder::new(new PdfSignatureValidationService($engine));
        $builder
            ->withPdfContent('pdf')
            ->withBrazilPolicy('/tmp/icp-brasil.pem')
            ->validate();

        self::assertInstanceOf(ValidatePdfRequestDto::class, $captured->request);
        self::assertTrue($captured->request->options->checkTrustChain);
        self::assertSame('/tmp/icp-brasil.pem', $captured->request->options->trustStorePath);
        self::assertSame(rtrim(sys_get_temp_dir(), '/').'/signer-php/trust-anchors', $captured->request->options->trustAnchorsDirectory);
        self::assertSame(BrazilTrustAnchorsOptionsDto::DEFAULT_URLS, $captured->request->options->trustAnchorsUrls);
        self::assertSame('br-iti', $captured->request->options->policy);
        self::assertTrue($captured->request->options->checkPolicyList);
        self::assertSame('https://politicas.icpbrasil.gov.br/LPA_PAdES.der', $captured->request->options->lpaUrlAsn1Pades);
        self::assertSame('https://politicas.icpbrasil.gov.br/LPA_PAdES.p7s', $captured->request->options->lpaUrlAsn1SignaturePades);
    }

    public function test_builder_can_override_brazil_policy_lpa_urls(): void
    {
        $captured = new class
        {
            public ?ValidatePdfRequestDto $request = null;
        };

        $engine = new class($captured) implements PdfSignatureValidationEngineInterface
        {
            public function __construct(private object $captured) {}

            public function validate(ValidatePdfRequestDto $request): SignatureValidationResultDto
            {
                $this->captured->request = $request;

                return new SignatureValidationResultDto(false, false, []);
            }
        };

        $builder = PdfSignatureValidatorBuilder::new(new PdfSignatureValidationService($engine));
        $builder
            ->withPdfContent('pdf')
            ->withBrazilPolicy('/tmp/icp-brasil.pem', new BrazilPolicyLpaUrlsDto(
                lpaUrlAsn1Pades: 'https://custom.example.com/LPA_PAdES.der',
                lpaUrlAsn1SignaturePades: 'https://custom.example.com/LPA_PAdES.p7s',
            ))
            ->validate();

        self::assertSame('https://custom.example.com/LPA_PAdES.der', $captured->request?->options->lpaUrlAsn1Pades);
        self::assertSame('https://custom.example.com/LPA_PAdES.p7s', $captured->request?->options->lpaUrlAsn1SignaturePades);
    }

    public function test_builder_can_override_brazil_policy_trust_anchors(): void
    {
        $captured = new class
        {
            public ?ValidatePdfRequestDto $request = null;
        };

        $engine = new class($captured) implements PdfSignatureValidationEngineInterface
        {
            public function __construct(private object $captured) {}

            public function validate(ValidatePdfRequestDto $request): SignatureValidationResultDto
            {
                $this->captured->request = $request;

                return new SignatureValidationResultDto(false, false, []);
            }
        };

        $builder = PdfSignatureValidatorBuilder::new(new PdfSignatureValidationService($engine));
        $builder
            ->withPdfContent('pdf')
            ->withBrazilPolicy(
                trustStorePath: null,
                lpaUrls: null,
                trustAnchors: new BrazilTrustAnchorsOptionsDto(
                    directory: '/tmp/custom-anchors',
                    urls: ['https://example.com/root-a.crt', 'https://example.com/root-b.crt'],
                )
            )
            ->validate();

        self::assertNull($captured->request?->options->trustStorePath);
        self::assertSame('/tmp/custom-anchors', $captured->request?->options->trustAnchorsDirectory);
        self::assertSame(['https://example.com/root-a.crt', 'https://example.com/root-b.crt'], $captured->request?->options->trustAnchorsUrls);
    }

    public function test_builder_can_disable_trust_chain_validation_after_enabling_it(): void
    {
        $captured = new class
        {
            public ?ValidatePdfRequestDto $request = null;
        };

        $engine = new class($captured) implements PdfSignatureValidationEngineInterface
        {
            public function __construct(private object $captured) {}

            public function validate(ValidatePdfRequestDto $request): SignatureValidationResultDto
            {
                $this->captured->request = $request;

                return new SignatureValidationResultDto(false, false, []);
            }
        };

        $builder = PdfSignatureValidatorBuilder::new(new PdfSignatureValidationService($engine));
        $builder
            ->withPdfContent('pdf')
            ->enableTrustChainValidation('/tmp/ca.pem')
            ->disableTrustChainValidation()
            ->validate();

        self::assertFalse($captured->request?->options->checkTrustChain);
        self::assertNull($captured->request?->options->trustStorePath);
        self::assertNull($captured->request?->options->policy);
        self::assertFalse($captured->request?->options->checkPolicyList);
    }

    private function fakeService(?SignatureValidationResultDto $result = null): PdfSignatureValidationService
    {
        $engine = new class($result ?? new SignatureValidationResultDto(false, false, [])) implements PdfSignatureValidationEngineInterface
        {
            public function __construct(private readonly SignatureValidationResultDto $result) {}

            public function validate(ValidatePdfRequestDto $request): SignatureValidationResultDto
            {
                return $this->result;
            }
        };

        return new PdfSignatureValidationService($engine);
    }
}

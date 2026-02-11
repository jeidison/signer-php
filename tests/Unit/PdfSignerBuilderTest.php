<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Application\Contract\CertificateValidatorInterface;
use PdfSigner\Application\Contract\DefaultTimestampOptionsProviderInterface;
use PdfSigner\Application\Contract\PdfProtectionEngineInterface;
use PdfSigner\Application\Contract\PdfSigningEngineInterface;
use PdfSigner\Application\DTO\BrazilSignaturePolicyOptionsDto;
use PdfSigner\Application\DTO\CertificateCredentialsDto;
use PdfSigner\Application\DTO\CertificationLevel;
use PdfSigner\Application\DTO\ProtectionOptionsDto;
use PdfSigner\Application\DTO\ProtectPdfRequestDto;
use PdfSigner\Application\DTO\SignatureAppearanceDto;
use PdfSigner\Application\DTO\SignatureMetadataDto;
use PdfSigner\Application\DTO\SignatureProfile;
use PdfSigner\Application\DTO\SigningContextDto;
use PdfSigner\Application\DTO\TimestampOptionsDto;
use PdfSigner\Application\Service\PdfProtectionService;
use PdfSigner\Application\Service\PdfSigningService;
use PdfSigner\Domain\Exception\PdfSignerException;
use PdfSigner\Domain\ValueObject\VerifiedCertificate;
use PdfSigner\Presentation\PdfSignerBuilder;
use PHPUnit\Framework\TestCase;

final class PdfSignerBuilderTest extends TestCase
{
    public function test_builder_requires_pdf_content(): void
    {
        $builder = PdfSignerBuilder::new($this->fakeService());

        $this->expectException(PdfSignerException::class);
        $builder->withCertificatePath('/tmp/cert.pfx', 'pwd')->sign();
    }

    public function test_builder_requires_certificate(): void
    {
        $builder = PdfSignerBuilder::new($this->fakeService());

        $this->expectException(PdfSignerException::class);
        $builder->withPdfContent('pdf')->sign();
    }

    public function test_builder_signs_when_all_required_inputs_are_present(): void
    {
        $builder = PdfSignerBuilder::new($this->fakeService('signed-ok'));

        $result = $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->sign();

        self::assertSame('signed-ok', $result);
    }

    public function test_builder_accepts_certificate_content(): void
    {
        $capture = new class
        {
            public ?CertificateCredentialsDto $credentials = null;
        };

        $validator = new class($capture) implements CertificateValidatorInterface
        {
            public function __construct(private object $capture) {}

            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                $this->capture->credentials = $credentials;

                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $engine = new class implements PdfSigningEngineInterface
        {
            public function sign(SigningContextDto $context): string
            {
                return 'signed';
            }
        };

        PdfSignerBuilder::new(new PdfSigningService($validator, $engine))
            ->withPdfContent('pdf')
            ->withCertificateContent('PKCS12-CONTENT', 'pwd')
            ->sign();

        self::assertNull($capture->credentials?->certificatePath);
        self::assertSame('PKCS12-CONTENT', $capture->credentials?->certificateContent);
    }

    public function test_builder_forwards_timestamp_options(): void
    {
        $capture = new class
        {
            public ?string $timestampUrl = null;

            public ?bool $useDefaultAppearance = null;

            public ?SignatureProfile $signatureProfile = null;
        };

        $validator = new class implements CertificateValidatorInterface
        {
            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $engine = new class($capture) implements PdfSigningEngineInterface
        {
            public function __construct(private object $capture) {}

            public function sign(SigningContextDto $context): string
            {
                $this->capture->timestampUrl = $context->request->options->timestamp?->tsaUrl;
                $this->capture->useDefaultAppearance = $context->request->options->useDefaultAppearance;
                $this->capture->signatureProfile = $context->request->options->signatureProfile;

                return 'signed';
            }
        };

        $builder = PdfSignerBuilder::new(new PdfSigningService($validator, $engine));
        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withTimestamp(new TimestampOptionsDto('https://tsa.example.com'))
            ->sign();

        self::assertSame('https://tsa.example.com', $capture->timestampUrl);
        self::assertTrue($capture->useDefaultAppearance);
        self::assertSame(SignatureProfile::PdfBasic, $capture->signatureProfile);
    }

    public function test_builder_can_enable_pades_baseline_b_profile(): void
    {
        $capture = new class
        {
            public ?SignatureProfile $signatureProfile = null;
        };

        $validator = new class implements CertificateValidatorInterface
        {
            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $engine = new class($capture) implements PdfSigningEngineInterface
        {
            public function __construct(private object $capture) {}

            public function sign(SigningContextDto $context): string
            {
                $this->capture->signatureProfile = $context->request->options->signatureProfile;

                return 'signed';
            }
        };

        $builder = PdfSignerBuilder::new(new PdfSigningService($validator, $engine));
        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withPadesBaselineB()
            ->sign();

        self::assertSame(SignatureProfile::PadesBaselineB, $capture->signatureProfile);
    }

    public function test_builder_can_enable_pades_baseline_t_profile(): void
    {
        $capture = new class
        {
            public ?SignatureProfile $signatureProfile = null;
        };

        $validator = new class implements CertificateValidatorInterface
        {
            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $engine = new class($capture) implements PdfSigningEngineInterface
        {
            public function __construct(private object $capture) {}

            public function sign(SigningContextDto $context): string
            {
                $this->capture->signatureProfile = $context->request->options->signatureProfile;

                return 'signed';
            }
        };

        $builder = PdfSignerBuilder::new(new PdfSigningService($validator, $engine));
        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withTimestamp()
            ->withPadesBaselineT()
            ->sign();

        self::assertSame(SignatureProfile::PadesBaselineT, $capture->signatureProfile);
    }

    public function test_builder_can_enable_pades_baseline_lt_profile(): void
    {
        $capture = new class
        {
            public ?SignatureProfile $signatureProfile = null;
        };

        $validator = new class implements CertificateValidatorInterface
        {
            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $engine = new class($capture) implements PdfSigningEngineInterface
        {
            public function __construct(private object $capture) {}

            public function sign(SigningContextDto $context): string
            {
                $this->capture->signatureProfile = $context->request->options->signatureProfile;

                return 'signed';
            }
        };

        $builder = PdfSignerBuilder::new(new PdfSigningService($validator, $engine));
        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withTimestamp()
            ->withPadesBaselineLT()
            ->sign();

        self::assertSame(SignatureProfile::PadesBaselineLT, $capture->signatureProfile);
    }

    public function test_builder_can_enable_pades_baseline_lta_profile(): void
    {
        $capture = new class
        {
            public ?SignatureProfile $signatureProfile = null;
        };

        $validator = new class implements CertificateValidatorInterface
        {
            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $engine = new class($capture) implements PdfSigningEngineInterface
        {
            public function __construct(private object $capture) {}

            public function sign(SigningContextDto $context): string
            {
                $this->capture->signatureProfile = $context->request->options->signatureProfile;

                return 'signed';
            }
        };

        $builder = PdfSignerBuilder::new(new PdfSigningService($validator, $engine));
        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withTimestamp()
            ->withPadesBaselineLTA()
            ->sign();

        self::assertSame(SignatureProfile::PadesBaselineLTA, $capture->signatureProfile);
    }

    public function test_builder_forwards_certification_level(): void
    {
        $capture = new class
        {
            public ?CertificationLevel $certificationLevel = null;
        };

        $validator = new class implements CertificateValidatorInterface
        {
            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $engine = new class($capture) implements PdfSigningEngineInterface
        {
            public function __construct(private object $capture) {}

            public function sign(SigningContextDto $context): string
            {
                $this->capture->certificationLevel = $context->request->options->certificationLevel;

                return 'signed';
            }
        };

        $builder = PdfSignerBuilder::new(new PdfSigningService($validator, $engine));
        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withCertificationLevel(CertificationLevel::FormFillAndSignatures)
            ->sign();

        self::assertSame(CertificationLevel::FormFillAndSignatures, $capture->certificationLevel);
    }

    public function test_builder_rejects_invalid_certification_level(): void
    {
        $builder = PdfSignerBuilder::new($this->fakeService());

        $this->expectException(PdfSignerException::class);
        $this->expectExceptionMessage('Certification level must be one of: 1, 2 or 3.');
        $builder->withCertificationLevel(4);
    }

    public function test_builder_applies_brazil_policy_preset(): void
    {
        $capture = new class
        {
            public ?SignatureProfile $signatureProfile = null;

            public ?CertificationLevel $certificationLevel = null;

            public ?string $timestampUrl = null;

            public ?string $oauthClientId = null;
        };

        $validator = new class implements CertificateValidatorInterface
        {
            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $engine = new class($capture) implements PdfSigningEngineInterface
        {
            public function __construct(private object $capture) {}

            public function sign(SigningContextDto $context): string
            {
                $this->capture->signatureProfile = $context->request->options->signatureProfile;
                $this->capture->certificationLevel = $context->request->options->certificationLevel;
                $this->capture->timestampUrl = $context->request->options->timestamp?->tsaUrl;
                $this->capture->oauthClientId = $context->request->options->timestamp?->oauthClientId;

                return 'signed';
            }
        };

        $builder = PdfSignerBuilder::new(new PdfSigningService($validator, $engine));
        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withBrazilPolicy(new BrazilSignaturePolicyOptionsDto('https://tsa.example.com'))
            ->sign();

        self::assertSame(SignatureProfile::PadesBaselineLTA, $capture->signatureProfile);
        self::assertSame(CertificationLevel::FormFillAndSignatures, $capture->certificationLevel);
        self::assertSame('https://tsa.example.com', $capture->timestampUrl);
        self::assertNull($capture->oauthClientId);
    }

    public function test_builder_applies_brazil_policy_preset_with_serpro_credentials(): void
    {
        $capture = new class
        {
            public ?string $timestampUrl = null;

            public ?string $oauthClientId = null;

            public ?string $oauthTokenUrl = null;
        };

        $validator = new class implements CertificateValidatorInterface
        {
            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $engine = new class($capture) implements PdfSigningEngineInterface
        {
            public function __construct(private object $capture) {}

            public function sign(SigningContextDto $context): string
            {
                $this->capture->timestampUrl = $context->request->options->timestamp?->tsaUrl;
                $this->capture->oauthClientId = $context->request->options->timestamp?->oauthClientId;
                $this->capture->oauthTokenUrl = $context->request->options->timestamp?->oauthTokenUrl;

                return 'signed';
            }
        };

        $builder = PdfSignerBuilder::new(new PdfSigningService($validator, $engine));
        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withBrazilPolicy(BrazilSignaturePolicyOptionsDto::serpro('key', 'secret'))
            ->sign();

        self::assertSame(BrazilSignaturePolicyOptionsDto::SERPRO_STAMP_URL, $capture->timestampUrl);
        self::assertSame('key', $capture->oauthClientId);
        self::assertSame(BrazilSignaturePolicyOptionsDto::SERPRO_TOKEN_URL, $capture->oauthTokenUrl);
    }

    public function test_pades_baseline_t_requires_timestamp_when_default_is_disabled(): void
    {
        $builder = PdfSignerBuilder::new($this->fakeService());

        $this->expectException(PdfSignerException::class);
        $this->expectExceptionMessage('PAdES Baseline-T requires timestamp');
        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withoutTimestamp()
            ->withPadesBaselineT()
            ->sign();
    }

    public function test_pades_baseline_lt_requires_timestamp_when_default_is_disabled(): void
    {
        $builder = PdfSignerBuilder::new($this->fakeService());

        $this->expectException(PdfSignerException::class);
        $this->expectExceptionMessage('PAdES Baseline-LT requires timestamp');
        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withoutTimestamp()
            ->withPadesBaselineLT()
            ->sign();
    }

    public function test_pades_baseline_lta_requires_timestamp_when_default_is_disabled(): void
    {
        $builder = PdfSignerBuilder::new($this->fakeService());

        $this->expectException(PdfSignerException::class);
        $this->expectExceptionMessage('PAdES Baseline-LTA requires timestamp');
        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withoutTimestamp()
            ->withPadesBaselineLTA()
            ->sign();
    }

    public function test_builder_does_not_apply_timestamp_by_default(): void
    {
        $capture = new class
        {
            public ?string $timestampUrl = null;
        };

        $validator = new class implements CertificateValidatorInterface
        {
            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $engine = new class($capture) implements PdfSigningEngineInterface
        {
            public function __construct(private object $capture) {}

            public function sign(SigningContextDto $context): string
            {
                $this->capture->timestampUrl = $context->request->options->timestamp?->tsaUrl;

                return 'signed';
            }
        };

        $builder = PdfSignerBuilder::new(new PdfSigningService($validator, $engine));
        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->sign();

        self::assertNull($capture->timestampUrl);
    }

    public function test_builder_without_timestamp_disables_explicit_timestamp(): void
    {
        $capture = new class
        {
            public ?string $timestampUrl = null;
        };

        $validator = new class implements CertificateValidatorInterface
        {
            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $engine = new class($capture) implements PdfSigningEngineInterface
        {
            public function __construct(private object $capture) {}

            public function sign(SigningContextDto $context): string
            {
                $this->capture->timestampUrl = $context->request->options->timestamp?->tsaUrl;

                return 'signed';
            }
        };

        $builder = PdfSignerBuilder::new(new PdfSigningService($validator, $engine));
        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withTimestamp(new TimestampOptionsDto('https://tsa.example.com'))
            ->withoutTimestamp()
            ->sign();

        self::assertNull($capture->timestampUrl);
    }

    public function test_builder_with_timestamp_null_enables_default_timestamp(): void
    {
        $capture = new class
        {
            public ?string $timestampUrl = null;
        };

        $validator = new class implements CertificateValidatorInterface
        {
            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $engine = new class($capture) implements PdfSigningEngineInterface
        {
            public function __construct(private object $capture) {}

            public function sign(SigningContextDto $context): string
            {
                $this->capture->timestampUrl = $context->request->options->timestamp?->tsaUrl;

                return 'signed';
            }
        };

        $builder = PdfSignerBuilder::new(new PdfSigningService($validator, $engine));
        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withoutTimestamp()
            ->withTimestamp()
            ->sign();

        self::assertSame('https://freetsa.org/tsr', $capture->timestampUrl);
    }

    public function test_builder_can_override_default_timestamp_profile(): void
    {
        $capture = new class
        {
            public ?string $timestampUrl = null;
        };

        $validator = new class implements CertificateValidatorInterface
        {
            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $engine = new class($capture) implements PdfSigningEngineInterface
        {
            public function __construct(private object $capture) {}

            public function sign(SigningContextDto $context): string
            {
                $this->capture->timestampUrl = $context->request->options->timestamp?->tsaUrl;

                return 'signed';
            }
        };

        $provider = new class implements DefaultTimestampOptionsProviderInterface
        {
            public function makeDefault(): TimestampOptionsDto
            {
                return new TimestampOptionsDto('https://provider.example.com');
            }
        };

        $builder = PdfSignerBuilder::new(
            new PdfSigningService($validator, $engine),
            null,
            $provider,
        );

        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withDefaultTimestampProfile(new TimestampOptionsDto('https://custom.example.com'))
            ->sign();

        self::assertSame('https://custom.example.com', $capture->timestampUrl);
    }

    public function test_builder_can_disable_default_appearance(): void
    {
        $capture = new class
        {
            public ?bool $useDefaultAppearance = null;
        };

        $validator = new class implements CertificateValidatorInterface
        {
            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $engine = new class($capture) implements PdfSigningEngineInterface
        {
            public function __construct(private object $capture) {}

            public function sign(SigningContextDto $context): string
            {
                $this->capture->useDefaultAppearance = $context->request->options->useDefaultAppearance;

                return 'signed';
            }
        };

        $builder = PdfSignerBuilder::new(new PdfSigningService($validator, $engine));
        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withoutDefaultAppearance()
            ->sign();

        self::assertFalse($capture->useDefaultAppearance);
    }

    public function test_protect_then_sign_runs_protection_before_signing(): void
    {
        $capture = new class
        {
            public ?string $protectedInput = null;

            public ?string $signedInput = null;
        };

        $validator = new class implements CertificateValidatorInterface
        {
            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $protectionEngine = new class($capture) implements PdfProtectionEngineInterface
        {
            public function __construct(private object $capture) {}

            public function protect(ProtectPdfRequestDto $request): string
            {
                $this->capture->protectedInput = $request->pdf->content;

                return 'protected-pdf';
            }
        };

        $signingEngine = new class($capture) implements PdfSigningEngineInterface
        {
            public function __construct(private object $capture) {}

            public function sign(SigningContextDto $context): string
            {
                $this->capture->signedInput = $context->request->pdf->content;

                return 'signed-pdf';
            }
        };

        $builder = PdfSignerBuilder::new(
            new PdfSigningService($validator, $signingEngine),
            new PdfProtectionService($protectionEngine),
        );

        $result = $builder
            ->withPdfContent('input-pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withProtection(ProtectionOptionsDto::preventCopy(ownerPassword: 'owner'))
            ->protectThenSign();

        self::assertSame('signed-pdf', $result);
        self::assertSame('input-pdf', $capture->protectedInput);
        self::assertSame('protected-pdf', $capture->signedInput);
    }

    public function test_protect_then_sign_requires_protection_options(): void
    {
        $builder = PdfSignerBuilder::new($this->fakeService());

        $this->expectException(PdfSignerException::class);
        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->protectThenSign();
    }

    public function test_builder_forwards_metadata_and_appearance(): void
    {
        $capture = new class
        {
            public ?SignatureMetadataDto $metadata = null;

            public ?SignatureAppearanceDto $appearance = null;
        };

        $validator = new class implements CertificateValidatorInterface
        {
            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $engine = new class($capture) implements PdfSigningEngineInterface
        {
            public function __construct(private object $capture) {}

            public function sign(SigningContextDto $context): string
            {
                $this->capture->metadata = $context->request->options->metadata;
                $this->capture->appearance = $context->request->options->appearance;

                return 'signed';
            }
        };

        $metadata = new SignatureMetadataDto('reason', 'location');
        $appearance = new SignatureAppearanceDto('img.png', [10, 20, 30, 40], 2);

        PdfSignerBuilder::new(new PdfSigningService($validator, $engine))
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withMetadata($metadata)
            ->withAppearance($appearance)
            ->sign();

        self::assertSame($metadata, $capture->metadata);
        self::assertSame($appearance, $capture->appearance);
    }

    public function test_sign_throws_when_protection_service_is_not_available(): void
    {
        $builder = PdfSignerBuilder::new($this->fakeService());

        $this->expectException(PdfSignerException::class);
        $this->expectExceptionMessage('Protection service is not available in this builder.');

        $builder
            ->withPdfContent('pdf')
            ->withCertificatePath('/tmp/cert.pfx', 'pwd')
            ->withProtection(ProtectionOptionsDto::preventCopy(ownerPassword: 'owner'))
            ->sign();
    }

    private function fakeService(string $output = 'signed'): PdfSigningService
    {
        $validator = new class implements CertificateValidatorInterface
        {
            public function validate(CertificateCredentialsDto $credentials): VerifiedCertificate
            {
                return new VerifiedCertificate($credentials, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => '']);
            }
        };

        $engine = new class($output) implements PdfSigningEngineInterface
        {
            public function __construct(private readonly string $output) {}

            public function sign(SigningContextDto $context): string
            {
                return $this->output;
            }
        };

        return new PdfSigningService($validator, $engine);
    }
}

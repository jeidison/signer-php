<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use SignerPHP\Application\DTO\CertificateCredentialsDto;
use SignerPHP\Application\DTO\CertificationLevel;
use SignerPHP\Application\DTO\PdfContentDto;
use SignerPHP\Application\DTO\SignatureAppearanceDto;
use SignerPHP\Application\DTO\SignatureProfile;
use SignerPHP\Application\DTO\SigningContextDto;
use SignerPHP\Application\DTO\SigningOptionsDto;
use SignerPHP\Application\DTO\SignPdfRequestDto;
use SignerPHP\Domain\ValueObject\VerifiedCertificate;
use SignerPHP\Infrastructure\Native\Contract\DefaultSignatureAppearanceProviderInterface;
use SignerPHP\Infrastructure\Native\Service\PdfSignatureFactory;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\Signature;
use SignerPHP\Infrastructure\PdfCore\SignatureAppearance;
use SignerPHP\Infrastructure\PdfCore\SignatureObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PdfSignatureFactoryTest extends TestCase
{
    public function test_factory_uses_default_appearance_when_none_is_provided(): void
    {
        $provider = new class implements DefaultSignatureAppearanceProviderInterface
        {
            public function makeDefault(): SignatureAppearanceDto
            {
                return new SignatureAppearanceDto('base64-image', [1, 2, 3, 4], 2);
            }
        };

        $factory = new PdfSignatureFactory($provider);
        $signature = $factory->create($this->context(new SigningOptionsDto), new PdfDocument);
        $appearance = $this->extractAppearance($signature);

        self::assertSame('base64-image', $appearance->getImage());
        self::assertSame([1, 2, 3, 4], $appearance->getRect());
        self::assertSame(2, $appearance->getPageToAppear());
    }

    public function test_factory_skips_default_appearance_when_disabled(): void
    {
        $provider = new class implements DefaultSignatureAppearanceProviderInterface
        {
            public function makeDefault(): SignatureAppearanceDto
            {
                return new SignatureAppearanceDto('base64-image', [1, 2, 3, 4], 2);
            }
        };

        $factory = new PdfSignatureFactory($provider);
        $signature = $factory->create(
            $this->context(new SigningOptionsDto(useDefaultAppearance: false)),
            new PdfDocument
        );
        $appearance = $this->extractAppearance($signature);

        self::assertNull($appearance->getImage());
        self::assertSame([0, 0, 0, 0], $appearance->getRect());
        self::assertSame(0, $appearance->getPageToAppear());
    }

    public function test_factory_prioritizes_explicit_appearance_over_default(): void
    {
        $provider = new class implements DefaultSignatureAppearanceProviderInterface
        {
            public function makeDefault(): SignatureAppearanceDto
            {
                return new SignatureAppearanceDto('default-image', [11, 22, 33, 44], 3);
            }
        };

        $factory = new PdfSignatureFactory($provider);
        $signature = $factory->create(
            $this->context(new SigningOptionsDto(
                appearance: new SignatureAppearanceDto('custom-image', [10, 20, 30, 40], 1),
                useDefaultAppearance: true,
            )),
            new PdfDocument
        );
        $appearance = $this->extractAppearance($signature);

        self::assertSame('custom-image', $appearance->getImage());
        self::assertSame([10, 20, 30, 40], $appearance->getRect());
        self::assertSame(1, $appearance->getPageToAppear());
    }

    public function test_factory_sets_pades_sub_filter_when_profile_is_enabled(): void
    {
        $provider = new class implements DefaultSignatureAppearanceProviderInterface
        {
            public function makeDefault(): SignatureAppearanceDto
            {
                return new SignatureAppearanceDto('base64-image', [1, 2, 3, 4], 2);
            }
        };

        $factory = new PdfSignatureFactory($provider);
        $signature = $factory->create(
            $this->context(new SigningOptionsDto(signatureProfile: SignatureProfile::PadesBaselineB)),
            new PdfDocument
        );

        self::assertSame(SignatureObject::SUBFILTER_ETSI_CADES_DETACHED, $this->extractSubFilter($signature));
    }

    public function test_factory_sets_pades_sub_filter_for_baseline_t_profile(): void
    {
        $provider = new class implements DefaultSignatureAppearanceProviderInterface
        {
            public function makeDefault(): SignatureAppearanceDto
            {
                return new SignatureAppearanceDto('base64-image', [1, 2, 3, 4], 2);
            }
        };

        $factory = new PdfSignatureFactory($provider);
        $signature = $factory->create(
            $this->context(new SigningOptionsDto(signatureProfile: SignatureProfile::PadesBaselineT)),
            new PdfDocument
        );

        self::assertSame(SignatureObject::SUBFILTER_ETSI_CADES_DETACHED, $this->extractSubFilter($signature));
    }

    public function test_factory_sets_pades_sub_filter_for_baseline_lt_profile(): void
    {
        $provider = new class implements DefaultSignatureAppearanceProviderInterface
        {
            public function makeDefault(): SignatureAppearanceDto
            {
                return new SignatureAppearanceDto('base64-image', [1, 2, 3, 4], 2);
            }
        };

        $factory = new PdfSignatureFactory($provider);
        $signature = $factory->create(
            $this->context(new SigningOptionsDto(signatureProfile: SignatureProfile::PadesBaselineLT)),
            new PdfDocument
        );

        self::assertSame(SignatureObject::SUBFILTER_ETSI_CADES_DETACHED, $this->extractSubFilter($signature));
    }

    public function test_factory_sets_pades_sub_filter_for_baseline_lta_profile(): void
    {
        $provider = new class implements DefaultSignatureAppearanceProviderInterface
        {
            public function makeDefault(): SignatureAppearanceDto
            {
                return new SignatureAppearanceDto('base64-image', [1, 2, 3, 4], 2);
            }
        };

        $factory = new PdfSignatureFactory($provider);
        $signature = $factory->create(
            $this->context(new SigningOptionsDto(signatureProfile: SignatureProfile::PadesBaselineLTA)),
            new PdfDocument
        );

        self::assertSame(SignatureObject::SUBFILTER_ETSI_CADES_DETACHED, $this->extractSubFilter($signature));
    }

    public function test_factory_forwards_certification_level_to_signature_state(): void
    {
        $provider = new class implements DefaultSignatureAppearanceProviderInterface
        {
            public function makeDefault(): SignatureAppearanceDto
            {
                return new SignatureAppearanceDto('base64-image', [1, 2, 3, 4], 2);
            }
        };

        $options = new SigningOptionsDto(certificationLevel: CertificationLevel::FormFillAndSignatures);
        $factory = new PdfSignatureFactory($provider);
        $signature = $factory->create($this->context($options), new PdfDocument);

        $reflection = new ReflectionClass($signature);
        $property = $reflection->getProperty('certificationLevel');
        $property->setAccessible(true);

        self::assertSame(CertificationLevel::FormFillAndSignatures, $property->getValue($signature));
    }

    private function context(SigningOptionsDto $options): SigningContextDto
    {
        $request = new SignPdfRequestDto(
            new PdfContentDto('pdf-content'),
            new CertificateCredentialsDto('/tmp/cert.pfx', 'pwd'),
            $options
        );

        return new SigningContextDto(
            $request,
            new VerifiedCertificate($request->certificate, ['validTo_time_t' => PHP_INT_MAX], ['cert' => '', 'pkey' => '', 'extracerts' => ''])
        );
    }

    private function extractAppearance(Signature $signature): SignatureAppearance
    {
        $reflection = new ReflectionClass($signature);
        $property = $reflection->getProperty('appearance');
        $property->setAccessible(true);

        /** @var SignatureAppearance $appearance */
        $appearance = $property->getValue($signature);

        return $appearance;
    }

    private function extractSubFilter(Signature $signature): string
    {
        $reflection = new ReflectionClass($signature);
        $property = $reflection->getProperty('subFilter');
        $property->setAccessible(true);

        return (string) $property->getValue($signature);
    }
}

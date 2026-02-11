<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use InvalidArgumentException;
use PdfSigner\Application\DTO\BrazilSignaturePolicyOptionsDto;
use PdfSigner\Application\DTO\BrazilTrustAnchorsOptionsDto;
use PdfSigner\Application\DTO\CertificateCredentialsDto;
use PdfSigner\Application\DTO\HashAlgorithm;
use PdfSigner\Application\DTO\PdfContentDto;
use PdfSigner\Application\DTO\ProtectionOptionsDto;
use PdfSigner\Application\DTO\SignatureActorDto;
use PdfSigner\Application\DTO\SignatureAppearanceDto;
use PdfSigner\Application\DTO\SignatureMetadataDto;
use PdfSigner\Application\DTO\SignatureProfile;
use PdfSigner\Application\DTO\SigningOptionsDto;
use PdfSigner\Application\DTO\SignPdfRequestDto;
use PdfSigner\Application\DTO\TimestampOptionsDto;
use PHPUnit\Framework\TestCase;

final class DtoTest extends TestCase
{
    public function test_certificate_credentials_dto_can_be_built_from_path_or_content(): void
    {
        $fromPath = CertificateCredentialsDto::fromPath('/tmp/cert.pfx', 'secret');
        self::assertSame('/tmp/cert.pfx', $fromPath->certificatePath);
        self::assertNull($fromPath->certificateContent);

        $fromContent = CertificateCredentialsDto::fromContent('PKCS12-BYTES', 'secret');
        self::assertNull($fromContent->certificatePath);
        self::assertSame('PKCS12-BYTES', $fromContent->certificateContent);
    }

    public function test_certificate_credentials_dto_requires_path_or_content(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Certificate path or content is required.');

        new CertificateCredentialsDto('', 'secret', '');
    }

    public function test_signature_appearance_normalizes_rect(): void
    {
        $appearance = new SignatureAppearanceDto('/tmp/a.png', [10, 20], 1);

        self::assertSame([10, 20, 0, 0], $appearance->normalizedRect());
    }

    public function test_signing_options_empty_factory(): void
    {
        $options = SigningOptionsDto::empty();

        self::assertNull($options->metadata);
        self::assertNull($options->appearance);
        self::assertNull($options->timestamp);
        self::assertTrue($options->useDefaultAppearance);
        self::assertSame(SignatureProfile::PdfBasic, $options->signatureProfile);
    }

    public function test_sign_request_from_required_uses_empty_options_when_missing(): void
    {
        $request = SignPdfRequestDto::fromRequired(
            new PdfContentDto('PDF'),
            new CertificateCredentialsDto('/tmp/cert.pfx', 'secret'),
        );

        self::assertSame('PDF', $request->pdf->content);
        self::assertNull($request->options->metadata);
        self::assertNull($request->options->appearance);
        self::assertNull($request->options->timestamp);
        self::assertTrue($request->options->useDefaultAppearance);
        self::assertSame(SignatureProfile::PdfBasic, $request->options->signatureProfile);
    }

    public function test_metadata_dto_carries_values(): void
    {
        $actor = new SignatureActorDto('A', 'D');
        $metadata = new SignatureMetadataDto('B', 'C', $actor);

        self::assertSame('B', $metadata->reason);
        self::assertSame('C', $metadata->location);
        self::assertSame('A', $metadata->actor?->name);
        self::assertSame('D', $metadata->actor?->contactInfo);
    }

    public function test_timestamp_options_dto_carries_values(): void
    {
        $timestamp = new TimestampOptionsDto(
            tsaUrl: 'https://tsa.example.com',
            hashAlgorithm: 'sha512',
            certReq: false,
            username: 'u',
            password: 'p',
            timeoutSeconds: 20,
        );

        self::assertSame('https://tsa.example.com', $timestamp->tsaUrl);
        self::assertSame('sha512', $timestamp->hashAlgorithm->value);
        self::assertFalse($timestamp->certReq);
        self::assertSame('u', $timestamp->username);
        self::assertSame('p', $timestamp->password);
        self::assertSame(20, $timestamp->timeoutSeconds);
    }

    public function test_brazil_signature_policy_options_dto_builds_timestamp_options(): void
    {
        $policy = new BrazilSignaturePolicyOptionsDto(
            tsaUrl: 'https://tsa.example.com',
            hashAlgorithm: 'sha512',
            timeoutSeconds: 30,
            certReq: false,
        );

        $timestamp = $policy->toTimestampOptions();

        self::assertSame('https://tsa.example.com', $timestamp->tsaUrl);
        self::assertSame('sha512', $timestamp->hashAlgorithm->value);
        self::assertSame(30, $timestamp->timeoutSeconds);
        self::assertFalse($timestamp->certReq);
    }

    public function test_brazil_signature_policy_options_dto_serpro_factory_builds_oauth_timestamp_options(): void
    {
        $policy = BrazilSignaturePolicyOptionsDto::serpro(
            consumerKey: 'key',
            consumerSecret: 'secret',
            hashAlgorithm: 'sha256',
            timeoutSeconds: 25,
        );

        $timestamp = $policy->toTimestampOptions();

        self::assertSame(BrazilSignaturePolicyOptionsDto::SERPRO_STAMP_URL, $timestamp->tsaUrl);
        self::assertSame('key', $timestamp->oauthClientId);
        self::assertSame('secret', $timestamp->oauthClientSecret);
        self::assertSame(BrazilSignaturePolicyOptionsDto::SERPRO_TOKEN_URL, $timestamp->oauthTokenUrl);
        self::assertSame(25, $timestamp->timeoutSeconds);
    }

    public function test_brazil_signature_policy_options_dto_tsa_factory_builds_non_oauth_timestamp_options(): void
    {
        $policy = BrazilSignaturePolicyOptionsDto::tsa(
            tsaUrl: 'https://tsa.custom.example.com',
            hashAlgorithm: 'sha512',
            timeoutSeconds: 18,
            certReq: true,
        );

        $timestamp = $policy->toTimestampOptions();

        self::assertSame('https://tsa.custom.example.com', $timestamp->tsaUrl);
        self::assertSame('sha512', $timestamp->hashAlgorithm->value);
        self::assertSame(18, $timestamp->timeoutSeconds);
        self::assertNull($timestamp->oauthClientId);
        self::assertNull($timestamp->oauthClientSecret);
        self::assertNull($timestamp->oauthTokenUrl);
    }

    public function test_brazil_signature_policy_options_dto_fluent_overrides(): void
    {
        $policy = BrazilSignaturePolicyOptionsDto::tsa('https://tsa.a.example.com')
            ->withTsa('https://tsa.b.example.com')
            ->withHashAlgorithm(HashAlgorithm::Sha384)
            ->withTimeoutSeconds(35)
            ->withOAuth('client', 'secret', 'https://oauth.example.com/token');

        $timestamp = $policy->toTimestampOptions();

        self::assertSame('https://tsa.b.example.com', $timestamp->tsaUrl);
        self::assertSame('sha384', $timestamp->hashAlgorithm->value);
        self::assertSame(35, $timestamp->timeoutSeconds);
        self::assertSame('client', $timestamp->oauthClientId);
        self::assertSame('secret', $timestamp->oauthClientSecret);
        self::assertSame('https://oauth.example.com/token', $timestamp->oauthTokenUrl);
    }

    public function test_brazil_trust_anchors_options_defaults_use_system_tmp(): void
    {
        $options = BrazilTrustAnchorsOptionsDto::defaults();

        self::assertSame(rtrim(sys_get_temp_dir(), '/').'/signer-php/trust-anchors', $options->directory);
        self::assertSame(BrazilTrustAnchorsOptionsDto::DEFAULT_URLS, $options->urls);
    }

    public function test_protection_options_dto_carries_values(): void
    {
        $options = new ProtectionOptionsDto(
            ownerPassword: 'owner-secret',
            userPassword: 'user-secret',
            allowPrint: false,
            allowCopy: false,
            allowModify: false,
            keyLengthBits: 128,
            encryptMetadata: false,
        );

        self::assertSame('owner-secret', $options->ownerPassword);
        self::assertSame('user-secret', $options->userPassword);
        self::assertFalse($options->allowPrint);
        self::assertFalse($options->allowCopy);
        self::assertFalse($options->allowModify);
        self::assertSame(128, $options->keyLengthBits);
        self::assertFalse($options->encryptMetadata);
    }

    public function test_protection_prevent_copy_factory_disables_copy_permission(): void
    {
        $options = ProtectionOptionsDto::preventCopy(ownerPassword: 'owner', userPassword: 'user');

        self::assertSame('owner', $options->ownerPassword);
        self::assertSame('user', $options->userPassword);
        self::assertFalse($options->allowCopy);
        self::assertTrue($options->allowPrint);
        self::assertTrue($options->allowModify);
    }

    public function test_protection_options_reject_unsupported_key_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProtectionOptionsDto(keyLengthBits: 40);
    }

    public function test_protection_options_reject_empty_owner_password(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner password cannot be an empty string.');

        new ProtectionOptionsDto(ownerPassword: '');
    }
}

<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use PdfSigner\Application\DTO\PdfContentDto;
use PdfSigner\Application\DTO\SignatureValidationOptionsDto;
use PdfSigner\Application\DTO\ValidatePdfRequestDto;
use PdfSigner\Domain\Exception\SignatureValidationException;
use PdfSigner\Infrastructure\Native\Contract\BrazilPolicyListVerifierInterface;
use PdfSigner\Infrastructure\Native\Contract\PdfSignatureExtractorInterface;
use PdfSigner\Infrastructure\Native\Contract\SignatureCryptoVerifierInterface;
use PdfSigner\Infrastructure\Native\Contract\SignatureTrustVerifierInterface;
use PdfSigner\Infrastructure\Native\NativePdfSignatureValidationEngine;
use PdfSigner\Infrastructure\Native\ValueObject\ExtractedPdfSignature;
use PdfSigner\Infrastructure\Native\ValueObject\SignatureCryptoVerification;
use PdfSigner\Infrastructure\Native\ValueObject\SignaturePolicyVerification;
use PdfSigner\Infrastructure\Native\ValueObject\SignatureTrustVerification;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NativePdfSignatureValidationEngineTest extends TestCase
{
    public function test_validate_returns_no_signatures_when_extractor_returns_empty(): void
    {
        $extractor = new class implements PdfSignatureExtractorInterface
        {
            public function extract(string $pdfContent): array
            {
                return [];
            }
        };

        $verifier = new class implements SignatureCryptoVerifierInterface
        {
            public function verify(string $signedContent, string $signatureHex): SignatureCryptoVerification
            {
                return new SignatureCryptoVerification(true);
            }
        };

        $trustVerifier = new class implements SignatureTrustVerifierInterface
        {
            public function verify(string $signatureHex, \PdfSigner\Application\DTO\SignatureValidationOptionsDto $options): SignatureTrustVerification
            {
                return new SignatureTrustVerification(true);
            }
        };
        $policyVerifier = new class implements BrazilPolicyListVerifierInterface
        {
            public function verifyPadesPolicy(SignatureValidationOptionsDto $options): SignaturePolicyVerification
            {
                return new SignaturePolicyVerification(true);
            }
        };

        $engine = new NativePdfSignatureValidationEngine($extractor, $verifier, $trustVerifier, $policyVerifier);
        $result = $engine->validate(new ValidatePdfRequestDto(new PdfContentDto('pdf')));

        self::assertFalse($result->hasSignatures);
        self::assertFalse($result->allValid);
        self::assertSame([], $result->entries);
    }

    public function test_validate_marks_entries_as_valid_when_byte_range_and_crypto_are_valid(): void
    {
        $extractor = new class implements PdfSignatureExtractorInterface
        {
            public function extract(string $pdfContent): array
            {
                return [
                    new ExtractedPdfSignature(0, [0, 10, 20, 5], 'ABCD', 'signed-content', true, null),
                ];
            }
        };

        $verifier = new class implements SignatureCryptoVerifierInterface
        {
            public function verify(string $signedContent, string $signatureHex): SignatureCryptoVerification
            {
                return new SignatureCryptoVerification(true);
            }
        };

        $trustVerifier = new class implements SignatureTrustVerifierInterface
        {
            public function verify(string $signatureHex, \PdfSigner\Application\DTO\SignatureValidationOptionsDto $options): SignatureTrustVerification
            {
                return new SignatureTrustVerification(true);
            }
        };
        $policyVerifier = new class implements BrazilPolicyListVerifierInterface
        {
            public function verifyPadesPolicy(SignatureValidationOptionsDto $options): SignaturePolicyVerification
            {
                return new SignaturePolicyVerification(true);
            }
        };

        $engine = new NativePdfSignatureValidationEngine($extractor, $verifier, $trustVerifier, $policyVerifier);
        $result = $engine->validate(new ValidatePdfRequestDto(new PdfContentDto('pdf')));

        self::assertTrue($result->hasSignatures);
        self::assertTrue($result->allValid);
        self::assertCount(1, $result->entries);
        self::assertTrue($result->entries[0]->valid);
        self::assertTrue($result->entries[0]->cryptoValid);
        self::assertTrue($result->entries[0]->trustValid);
        self::assertNull($result->entries[0]->policyValid);
        self::assertTrue($result->entries[0]->byteRangeValid);
    }

    public function test_validate_wraps_unexpected_errors(): void
    {
        $extractor = new class implements PdfSignatureExtractorInterface
        {
            public function extract(string $pdfContent): array
            {
                throw new RuntimeException('boom');
            }
        };

        $verifier = new class implements SignatureCryptoVerifierInterface
        {
            public function verify(string $signedContent, string $signatureHex): SignatureCryptoVerification
            {
                return new SignatureCryptoVerification(true);
            }
        };

        $trustVerifier = new class implements SignatureTrustVerifierInterface
        {
            public function verify(string $signatureHex, \PdfSigner\Application\DTO\SignatureValidationOptionsDto $options): SignatureTrustVerification
            {
                return new SignatureTrustVerification(true);
            }
        };
        $policyVerifier = new class implements BrazilPolicyListVerifierInterface
        {
            public function verifyPadesPolicy(SignatureValidationOptionsDto $options): SignaturePolicyVerification
            {
                return new SignaturePolicyVerification(true);
            }
        };

        $engine = new NativePdfSignatureValidationEngine($extractor, $verifier, $trustVerifier, $policyVerifier);

        $this->expectException(SignatureValidationException::class);
        $this->expectExceptionMessage('Root cause: boom');
        $engine->validate(new ValidatePdfRequestDto(new PdfContentDto('pdf')));
    }

    public function test_validate_enforces_brazil_policy_trust_check(): void
    {
        $extractor = new class implements PdfSignatureExtractorInterface
        {
            public function extract(string $pdfContent): array
            {
                return [
                    new ExtractedPdfSignature(0, [0, 10, 20, 5], 'ABCD', 'signed-content', true, null),
                ];
            }
        };

        $verifier = new class implements SignatureCryptoVerifierInterface
        {
            public function verify(string $signedContent, string $signatureHex): SignatureCryptoVerification
            {
                return new SignatureCryptoVerification(true);
            }
        };

        $trustVerifier = new class implements SignatureTrustVerifierInterface
        {
            public function verify(string $signatureHex, SignatureValidationOptionsDto $options): SignatureTrustVerification
            {
                return new SignatureTrustVerification(false, 'untrusted');
            }
        };
        $policyVerifier = new class implements BrazilPolicyListVerifierInterface
        {
            public function verifyPadesPolicy(SignatureValidationOptionsDto $options): SignaturePolicyVerification
            {
                return new SignaturePolicyVerification(true);
            }
        };

        $engine = new NativePdfSignatureValidationEngine($extractor, $verifier, $trustVerifier, $policyVerifier);
        $result = $engine->validate(new ValidatePdfRequestDto(
            new PdfContentDto('pdf'),
            new SignatureValidationOptionsDto(checkTrustChain: true, trustStorePath: '/tmp/x.pem', policy: 'br-iti')
        ));

        self::assertFalse($result->allValid);
        self::assertFalse($result->entries[0]->valid);
        self::assertFalse($result->entries[0]->trustValid ?? true);
    }

    public function test_validate_enforces_brazil_policy_lpa_verification(): void
    {
        $extractor = new class implements PdfSignatureExtractorInterface
        {
            public function extract(string $pdfContent): array
            {
                return [
                    new ExtractedPdfSignature(0, [0, 10, 20, 5], 'ABCD', 'signed-content', true, null),
                ];
            }
        };

        $verifier = new class implements SignatureCryptoVerifierInterface
        {
            public function verify(string $signedContent, string $signatureHex): SignatureCryptoVerification
            {
                return new SignatureCryptoVerification(true);
            }
        };

        $trustVerifier = new class implements SignatureTrustVerifierInterface
        {
            public function verify(string $signatureHex, SignatureValidationOptionsDto $options): SignatureTrustVerification
            {
                return new SignatureTrustVerification(true);
            }
        };

        $policyVerifier = new class implements BrazilPolicyListVerifierInterface
        {
            public function verifyPadesPolicy(SignatureValidationOptionsDto $options): SignaturePolicyVerification
            {
                return new SignaturePolicyVerification(false, 'LPA invalid');
            }
        };

        $engine = new NativePdfSignatureValidationEngine($extractor, $verifier, $trustVerifier, $policyVerifier);
        $result = $engine->validate(new ValidatePdfRequestDto(
            new PdfContentDto('pdf'),
            new SignatureValidationOptionsDto(
                checkTrustChain: true,
                trustStorePath: '/tmp/x.pem',
                policy: 'br-iti',
                checkPolicyList: true,
                lpaUrlAsn1Pades: 'https://politicas.icpbrasil.gov.br/LPA_PAdES.der',
                lpaUrlAsn1SignaturePades: 'https://politicas.icpbrasil.gov.br/LPA_PAdES.p7s',
            )
        ));

        self::assertFalse($result->allValid);
        self::assertFalse($result->entries[0]->valid);
        self::assertFalse($result->entries[0]->policyValid ?? true);
        self::assertSame('LPA invalid', $result->entries[0]->reason);
    }

    public function test_validate_uses_byte_range_error_when_byte_range_is_invalid(): void
    {
        $extractor = new class implements PdfSignatureExtractorInterface
        {
            public function extract(string $pdfContent): array
            {
                return [
                    new ExtractedPdfSignature(1, [0, 10, 20, 5], 'ABCD', 'signed-content', false, 'invalid-byte-range'),
                ];
            }
        };

        $cryptoCalled = new class
        {
            public bool $value = false;
        };

        $cryptoVerifier = new class($cryptoCalled) implements SignatureCryptoVerifierInterface
        {
            public function __construct(private object $state) {}

            public function verify(string $signedContent, string $signatureHex): SignatureCryptoVerification
            {
                $this->state->value = true;

                return new SignatureCryptoVerification(true);
            }
        };

        $trustVerifier = new class implements SignatureTrustVerifierInterface
        {
            public function verify(string $signatureHex, SignatureValidationOptionsDto $options): SignatureTrustVerification
            {
                return new SignatureTrustVerification(true);
            }
        };

        $policyVerifier = new class implements BrazilPolicyListVerifierInterface
        {
            public function verifyPadesPolicy(SignatureValidationOptionsDto $options): SignaturePolicyVerification
            {
                return new SignaturePolicyVerification(true);
            }
        };

        $engine = new NativePdfSignatureValidationEngine($extractor, $cryptoVerifier, $trustVerifier, $policyVerifier);
        $result = $engine->validate(new ValidatePdfRequestDto(new PdfContentDto('pdf')));

        self::assertFalse($cryptoCalled->value);
        self::assertFalse($result->entries[0]->valid);
        self::assertFalse($result->entries[0]->cryptoValid);
        self::assertSame('invalid-byte-range', $result->entries[0]->reason);
    }

    public function test_validate_uses_crypto_message_when_signature_crypto_is_invalid(): void
    {
        $extractor = new class implements PdfSignatureExtractorInterface
        {
            public function extract(string $pdfContent): array
            {
                return [
                    new ExtractedPdfSignature(2, [0, 10, 20, 5], 'ABCD', 'signed-content', true, null),
                ];
            }
        };

        $trustCalled = new class
        {
            public bool $value = false;
        };

        $cryptoVerifier = new class implements SignatureCryptoVerifierInterface
        {
            public function verify(string $signedContent, string $signatureHex): SignatureCryptoVerification
            {
                return new SignatureCryptoVerification(false, 'crypto-invalid');
            }
        };

        $trustVerifier = new class($trustCalled) implements SignatureTrustVerifierInterface
        {
            public function __construct(private object $state) {}

            public function verify(string $signatureHex, SignatureValidationOptionsDto $options): SignatureTrustVerification
            {
                $this->state->value = true;

                return new SignatureTrustVerification(true);
            }
        };

        $policyVerifier = new class implements BrazilPolicyListVerifierInterface
        {
            public function verifyPadesPolicy(SignatureValidationOptionsDto $options): SignaturePolicyVerification
            {
                return new SignaturePolicyVerification(true);
            }
        };

        $engine = new NativePdfSignatureValidationEngine($extractor, $cryptoVerifier, $trustVerifier, $policyVerifier);
        $result = $engine->validate(new ValidatePdfRequestDto(new PdfContentDto('pdf')));

        self::assertFalse($trustCalled->value);
        self::assertFalse($result->entries[0]->cryptoValid);
        self::assertNull($result->entries[0]->trustValid);
        self::assertSame('crypto-invalid', $result->entries[0]->reason);
    }
}

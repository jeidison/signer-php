<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native;

use PdfSigner\Application\Contract\PdfSignatureValidationEngineInterface;
use PdfSigner\Application\DTO\SignatureValidationEntryDto;
use PdfSigner\Application\DTO\SignatureValidationResultDto;
use PdfSigner\Application\DTO\ValidatePdfRequestDto;
use PdfSigner\Domain\Exception\SignatureValidationException;
use PdfSigner\Infrastructure\Native\Contract\BrazilPolicyListVerifierInterface;
use PdfSigner\Infrastructure\Native\Contract\PdfSignatureExtractorInterface;
use PdfSigner\Infrastructure\Native\Contract\SignatureCryptoVerifierInterface;
use PdfSigner\Infrastructure\Native\Contract\SignatureTrustVerifierInterface;
use PdfSigner\Infrastructure\Native\Service\OpenSslBrazilPolicyListVerifier;
use PdfSigner\Infrastructure\Native\Service\OpenSslSignatureCryptoVerifier;
use PdfSigner\Infrastructure\Native\Service\OpenSslSignatureTrustVerifier;
use PdfSigner\Infrastructure\Native\Service\PdfSignatureExtractor;

final readonly class NativePdfSignatureValidationEngine implements PdfSignatureValidationEngineInterface
{
    public function __construct(
        private PdfSignatureExtractorInterface $signatureExtractor = new PdfSignatureExtractor,
        private SignatureCryptoVerifierInterface $cryptoVerifier = new OpenSslSignatureCryptoVerifier,
        private SignatureTrustVerifierInterface $trustVerifier = new OpenSslSignatureTrustVerifier,
        private BrazilPolicyListVerifierInterface $policyVerifier = new OpenSslBrazilPolicyListVerifier,
    ) {}

    public function validate(ValidatePdfRequestDto $request): SignatureValidationResultDto
    {
        try {
            $extracted = $this->signatureExtractor->extract($request->pdf->content);
            if ($extracted === []) {
                return new SignatureValidationResultDto(false, false, []);
            }

            $entries = [];
            $policy = ($request->options->policy === 'br-iti' && $request->options->checkPolicyList)
                ? $this->policyVerifier->verifyPadesPolicy($request->options)
                : null;

            foreach ($extracted as $signature) {
                $crypto = $signature->byteRangeValid
                    ? $this->cryptoVerifier->verify($signature->signedContent, $signature->signatureHex)
                    : null;

                $reason = $signature->byteRangeValid
                    ? $crypto?->message
                    : $signature->byteRangeError;

                $cryptoValid = $signature->byteRangeValid && ($crypto?->valid ?? false);
                $trust = ($signature->byteRangeValid && $cryptoValid)
                    ? $this->trustVerifier->verify($signature->signatureHex, $request->options)
                    : null;
                $trustValid = $trust?->valid;
                if ($signature->byteRangeValid && $cryptoValid && $trustValid === false) {
                    $reason = $trust?->message;
                }

                if ($request->options->policy === 'br-iti' && $signature->byteRangeValid && $cryptoValid && $trustValid !== true) {
                    $trustValid = false;
                    $reason = $reason ?? 'BR-ITI policy requires trusted certificate chain validation.';
                }

                $policyValid = $policy?->valid;
                if ($request->options->policy === 'br-iti' && $request->options->checkPolicyList && $policyValid !== true) {
                    $policyValid = false;
                    $reason = $policy?->message ?? 'BR-ITI policy list verification failed.';
                }

                $valid = $signature->byteRangeValid && $cryptoValid && ($trustValid ?? true) && ($policyValid ?? true);

                $entries[] = new SignatureValidationEntryDto(
                    index: $signature->index,
                    byteRange: $signature->byteRange,
                    byteRangeValid: $signature->byteRangeValid,
                    cryptoValid: $cryptoValid,
                    trustValid: $trustValid,
                    policyValid: $policyValid,
                    valid: $valid,
                    reason: $reason
                );
            }

            $allValid = true;
            foreach ($entries as $entry) {
                if (! $entry->valid) {
                    $allValid = false;
                    break;
                }
            }

            return new SignatureValidationResultDto(true, $allValid, $entries);
        } catch (\Throwable $throwable) {
            throw new SignatureValidationException(
                sprintf('Could not validate PDF signatures using native v1 engine. Root cause: %s', $throwable->getMessage()),
                previous: $throwable,
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native;

use SignerPHP\Application\Contract\PdfSignatureValidationEngineInterface;
use SignerPHP\Application\DTO\SignatureValidationEntryDto;
use SignerPHP\Application\DTO\SignatureValidationResultDto;
use SignerPHP\Application\DTO\ValidatePdfRequestDto;
use SignerPHP\Domain\Exception\SignatureValidationException;
use SignerPHP\Infrastructure\Native\Contract\BrazilPolicyListVerifierInterface;
use SignerPHP\Infrastructure\Native\Contract\PdfSignatureExtractorInterface;
use SignerPHP\Infrastructure\Native\Contract\SignatureCryptoVerifierInterface;
use SignerPHP\Infrastructure\Native\Contract\SignatureTrustVerifierInterface;
use SignerPHP\Infrastructure\Native\Service\OpenSslBrazilPolicyListVerifier;
use SignerPHP\Infrastructure\Native\Service\OpenSslSignatureCryptoVerifier;
use SignerPHP\Infrastructure\Native\Service\OpenSslSignatureTrustVerifier;
use SignerPHP\Infrastructure\Native\Service\PdfSignatureExtractor;

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

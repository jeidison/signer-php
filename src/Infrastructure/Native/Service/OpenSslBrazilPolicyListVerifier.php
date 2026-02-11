<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Service;

use PdfSigner\Application\DTO\SignatureValidationOptionsDto;
use PdfSigner\Infrastructure\Native\Contract\BrazilPolicyListVerifierInterface;
use PdfSigner\Infrastructure\Native\Contract\HttpClientInterface;
use PdfSigner\Infrastructure\Native\Contract\ProcessRunnerInterface;
use PdfSigner\Infrastructure\Native\ValueObject\SignaturePolicyVerification;

final class OpenSslBrazilPolicyListVerifier implements BrazilPolicyListVerifierInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient = new CurlHttpClient,
        private readonly ProcessRunnerInterface $processRunner = new ShellProcessRunner,
    ) {}

    public function verifyPadesPolicy(SignatureValidationOptionsDto $options): SignaturePolicyVerification
    {
        $lpaUrl = $options->lpaUrlAsn1Pades;
        $sigUrl = $options->lpaUrlAsn1SignaturePades;
        if ($lpaUrl === null || $sigUrl === null) {
            return new SignaturePolicyVerification(false, 'LPA PAdES URLs are not configured for BR-ITI policy.');
        }

        $lpa = $this->download($lpaUrl, 20);
        if ($lpa === null) {
            return new SignaturePolicyVerification(false, 'Could not download LPA PAdES ASN.1 from '.$lpaUrl);
        }

        $signature = $this->download($sigUrl, 20);
        if ($signature === null) {
            return new SignaturePolicyVerification(false, 'Could not download LPA PAdES signature from '.$sigUrl);
        }

        $tmpDir = sys_get_temp_dir();
        $lpaFile = tempnam($tmpDir, 'lpa-pades');
        $sigFile = tempnam($tmpDir, 'lpa-pades-sig');
        $outFile = tempnam($tmpDir, 'lpa-pades-out');

        if ($lpaFile === false || $sigFile === false || $outFile === false) {
            return new SignaturePolicyVerification(false, 'Could not create temporary files for LPA verification.');
        }

        file_put_contents($lpaFile, $lpa);
        file_put_contents($sigFile, $signature);

        try {
            $derCommand = sprintf(
                'openssl cms -verify -binary -inform DER -in %s -content %s -noverify -out %s',
                escapeshellarg($sigFile),
                escapeshellarg($lpaFile),
                escapeshellarg($outFile),
            );
            if ($this->runCommand($derCommand)) {
                return new SignaturePolicyVerification(true);
            }

            $smimeCommand = sprintf(
                'openssl cms -verify -binary -in %s -content %s -noverify -out %s',
                escapeshellarg($sigFile),
                escapeshellarg($lpaFile),
                escapeshellarg($outFile),
            );
            if ($this->runCommand($smimeCommand)) {
                return new SignaturePolicyVerification(true);
            }

            return new SignaturePolicyVerification(false, 'LPA PAdES signature verification failed.');
        } finally {
            @unlink($lpaFile);
            @unlink($sigFile);
            @unlink($outFile);
        }
    }

    private function download(string $url, int $timeoutSeconds): ?string
    {
        $response = $this->httpClient->request('GET', $url, [], '', $timeoutSeconds);
        if (! $response->isSuccessful() || $response->body === '' || $response->transportError !== null) {
            return null;
        }

        return $response->body;
    }

    private function runCommand(string $command): bool
    {
        return $this->processRunner->run($command)->succeeded();
    }
}

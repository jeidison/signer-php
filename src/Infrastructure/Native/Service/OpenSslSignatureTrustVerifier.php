<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Service;

use PdfSigner\Application\DTO\SignatureValidationOptionsDto;
use PdfSigner\Infrastructure\Native\Contract\ProcessRunnerInterface;
use PdfSigner\Infrastructure\Native\Contract\SignatureCertificateCollectorInterface;
use PdfSigner\Infrastructure\Native\Contract\SignatureTrustVerifierInterface;
use PdfSigner\Infrastructure\Native\ValueObject\SignatureTrustVerification;

final readonly class OpenSslSignatureTrustVerifier implements SignatureTrustVerifierInterface
{
    public function __construct(
        private SignatureCertificateCollectorInterface $certificateCollector = new OpenSslCmsCertificateCollector,
        private IcpBrasilTrustAnchorBundleProvider $trustAnchorBundleProvider = new IcpBrasilTrustAnchorBundleProvider,
        private ProcessRunnerInterface $processRunner = new ShellProcessRunner,
    ) {}

    public function verify(string $signatureHex, SignatureValidationOptionsDto $options): SignatureTrustVerification
    {
        if (! $options->checkTrustChain) {
            return new SignatureTrustVerification(true);
        }

        $trustStore = $this->resolveTrustStore($options);
        if ($trustStore === null) {
            return new SignatureTrustVerification(false, 'No trust store found for certificate chain validation.');
        }

        $certificatesDer = $this->certificateCollector->collectDerCertificates($signatureHex);
        if ($certificatesDer === []) {
            return new SignatureTrustVerification(false, 'Could not extract certificates from signature CMS payload.');
        }

        $leafPem = $this->derToPem($certificatesDer[0]);
        if ($leafPem === null) {
            return new SignatureTrustVerification(false, 'Could not decode signer certificate from CMS payload.');
        }

        $chainPem = '';
        foreach (array_slice($certificatesDer, 1) as $certDer) {
            $pem = $this->derToPem($certDer);
            if ($pem !== null) {
                $chainPem .= $pem;
            }
        }

        $tmpDir = sys_get_temp_dir();
        $leafFile = tempnam($tmpDir, 'pdf-sig-leaf');
        if ($leafFile === false) {
            return new SignatureTrustVerification(false, 'Could not create temporary file for signer certificate.');
        }

        file_put_contents($leafFile, $leafPem);

        $chainFile = null;
        if ($chainPem !== '') {
            $chainFile = tempnam($tmpDir, 'pdf-sig-chain');
            if ($chainFile !== false) {
                file_put_contents($chainFile, $chainPem);
            }
        }

        try {
            $command = sprintf(
                'openssl verify -CAfile %s%s %s',
                escapeshellarg($trustStore),
                $chainFile !== null ? ' -untrusted '.escapeshellarg($chainFile) : '',
                escapeshellarg($leafFile),
            );

            $result = $this->processRunner->run($command);
            if (! $result->succeeded()) {
                $message = trim($result->outputAsString());
                if ($message === '') {
                    $message = 'OpenSSL verify failed for signer certificate chain.';
                }

                return new SignatureTrustVerification(false, $message);
            }

            return new SignatureTrustVerification(true);
        } finally {
            @unlink($leafFile);
            if ($chainFile !== null) {
                @unlink($chainFile);
            }
        }
    }

    private function resolveTrustStore(SignatureValidationOptionsDto $options): ?string
    {
        $explicitPath = $options->trustStorePath;
        if ($explicitPath !== null) {
            return is_file($explicitPath) ? $explicitPath : null;
        }

        if ($options->policy === 'br-iti') {
            $directory = $options->trustAnchorsDirectory ?? rtrim(sys_get_temp_dir(), '/').'/signer-php/trust-anchors';
            $urls = $options->trustAnchorsUrls ?? [];
            if ($urls !== []) {
                $bundle = $this->trustAnchorBundleProvider->resolveBundle($directory, $urls);
                if ($bundle !== null && is_file($bundle)) {
                    return $bundle;
                }
            }
        }

        $candidates = [
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/ca-bundle.pem',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function derToPem(string $der): ?string
    {
        if ($der === '') {
            return null;
        }

        return "-----BEGIN CERTIFICATE-----\n".
            chunk_split(base64_encode($der), 64, "\n").
            "-----END CERTIFICATE-----\n";
    }
}

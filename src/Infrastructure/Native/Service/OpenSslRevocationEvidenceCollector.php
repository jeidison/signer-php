<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Service;

use PdfSigner\Infrastructure\Native\Contract\HttpClientInterface;
use PdfSigner\Infrastructure\Native\Contract\ProcessRunnerInterface;
use PdfSigner\Infrastructure\Native\Contract\SignatureRevocationEvidenceCollectorInterface;

final readonly class OpenSslRevocationEvidenceCollector implements SignatureRevocationEvidenceCollectorInterface
{
    public function __construct(
        private X509ExtensionUrlExtractor $urlExtractor = new X509ExtensionUrlExtractor,
        private HttpClientInterface $httpClient = new CurlHttpClient,
        private ProcessRunnerInterface $processRunner = new ShellProcessRunner,
    ) {}

    public function collect(array $certificateChainDer): array
    {
        $result = [];

        foreach ($certificateChainDer as $index => $certDer) {
            $parsed = $this->parseCertificate($certDer);
            if ($parsed === null) {
                $result[$index] = ['ocsp' => [], 'crl' => []];

                continue;
            }

            $crls = $this->collectCrls($this->urlExtractor->crlUrls($parsed));
            $ocsps = $this->collectOcspResponses(
                $certDer,
                $this->issuerCandidates($certificateChainDer, $index),
                $this->urlExtractor->ocspUrls($parsed)
            );

            $result[$index] = [
                'ocsp' => $ocsps,
                'crl' => $crls,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseCertificate(string $certDer): ?array
    {
        $pem = $this->derToPem($certDer);
        if ($pem === null) {
            return null;
        }

        $parsed = @openssl_x509_parse($pem, false);
        if (! is_array($parsed)) {
            return null;
        }

        return $parsed;
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

    /**
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    private function collectCrls(array $urls): array
    {
        $responses = [];

        foreach ($urls as $url) {
            $body = $this->fetchBinaryViaCurl($url, 10);
            if ($body === false || $body === '') {
                continue;
            }

            $responses[] = $body;
        }

        return $this->dedupeBinary($responses);
    }

    private function fetchBinaryViaCurl(string $url, int $timeoutSeconds): string|false
    {
        $response = $this->httpClient->request('GET', $url, [], '', $timeoutSeconds, true);
        if (! $response->isSuccessful() || $response->body === '' || $response->transportError !== null) {
            return false;
        }

        return $response->body;
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    private function collectOcspResponses(string $certDer, array $issuerCandidatesDer, array $urls): array
    {
        if ($issuerCandidatesDer === [] || $urls === []) {
            return [];
        }

        $tmpDir = sys_get_temp_dir();
        $certPem = $this->derToPem($certDer);
        if ($certPem === null) {
            return [];
        }

        $certFile = tempnam($tmpDir, 'ocsp-cert');
        if ($certFile === false) {
            return [];
        }

        file_put_contents($certFile, $certPem);

        $responses = [];
        try {
            foreach ($issuerCandidatesDer as $issuerDer) {
                $issuerPem = $this->derToPem($issuerDer);
                if ($issuerPem === null) {
                    continue;
                }

                $issuerFile = tempnam($tmpDir, 'ocsp-issuer');
                if ($issuerFile === false) {
                    continue;
                }

                file_put_contents($issuerFile, $issuerPem);

                try {
                    foreach ($urls as $url) {
                        $respFile = tempnam($tmpDir, 'ocsp-resp');
                        if ($respFile === false) {
                            continue;
                        }

                        try {
                            $command = sprintf(
                                'openssl ocsp -issuer %s -cert %s -url %s -respout %s -noverify -no_nonce',
                                escapeshellarg($issuerFile),
                                escapeshellarg($certFile),
                                escapeshellarg($url),
                                escapeshellarg($respFile),
                            );

                            $result = $this->processRunner->run($command);
                            if (! $result->succeeded()) {
                                continue;
                            }

                            $respDer = @file_get_contents($respFile);
                            if ($respDer === false || $respDer === '') {
                                continue;
                            }

                            $responses[] = $respDer;
                        } finally {
                            @unlink($respFile);
                        }
                    }
                } finally {
                    @unlink($issuerFile);
                }
            }
        } finally {
            @unlink($certFile);
        }

        return $this->dedupeBinary($responses);
    }

    /**
     * @param  array<int, string>  $chain
     * @return array<int, string>
     */
    private function issuerCandidates(array $chain, int $certIndex): array
    {
        $ordered = [];

        for ($i = $certIndex + 1; $i < count($chain); $i++) {
            $ordered[] = $chain[$i];
        }

        for ($i = 0; $i < count($chain); $i++) {
            if ($i === $certIndex || $i > $certIndex) {
                continue;
            }

            $ordered[] = $chain[$i];
        }

        return $this->dedupeBinary($ordered);
    }

    /**
     * @param  array<int, string>  $items
     * @return array<int, string>
     */
    private function dedupeBinary(array $items): array
    {
        $unique = [];
        foreach ($items as $item) {
            if ($item === '') {
                continue;
            }

            $unique[hash('sha256', $item)] = $item;
        }

        return array_values($unique);
    }
}

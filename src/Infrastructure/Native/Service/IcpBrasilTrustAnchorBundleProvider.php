<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Service;

use PdfSigner\Infrastructure\Native\Contract\HttpClientInterface;

final class IcpBrasilTrustAnchorBundleProvider
{
    public function __construct(
        private readonly HttpClientInterface $httpClient = new CurlHttpClient,
    ) {}

    /**
     * @param  array<int, string>  $urls
     */
    public function resolveBundle(string $directory, array $urls): ?string
    {
        $directory = rtrim($directory, '/');
        if ($directory === '') {
            return null;
        }

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return null;
        }

        foreach ($urls as $url) {
            $content = $this->download($url, 12);
            if ($content === null) {
                continue;
            }

            $pem = $this->toPem($content);
            if ($pem === null) {
                continue;
            }

            $target = $directory.'/anchor-'.hash('sha256', $url).'.pem';
            @file_put_contents($target, $pem);
        }

        $bundle = $this->buildBundleFromDirectory($directory);
        if ($bundle === '') {
            return null;
        }

        $bundlePath = $directory.'/trust-anchors-bundle.pem';
        if (@file_put_contents($bundlePath, $bundle) === false) {
            return null;
        }

        return $bundlePath;
    }

    private function buildBundleFromDirectory(string $directory): string
    {
        $files = glob($directory.'/*.pem');
        if (! is_array($files) || $files === []) {
            return '';
        }

        sort($files);
        $bundle = '';
        foreach ($files as $file) {
            if (basename($file) === 'trust-anchors-bundle.pem') {
                continue;
            }

            $content = @file_get_contents($file);
            if (! is_string($content) || $content === '') {
                continue;
            }

            if (@openssl_x509_read($content) === false) {
                continue;
            }

            $bundle .= rtrim($content)."\n";
        }

        return $bundle;
    }

    private function download(string $url, int $timeoutSeconds): ?string
    {
        $response = $this->httpClient->request('GET', $url, [], '', $timeoutSeconds, true);
        if (! $response->isSuccessful() || $response->body === '' || $response->transportError !== null) {
            return null;
        }

        return $response->body;
    }

    private function toPem(string $certificateBytes): ?string
    {
        if ($certificateBytes === '') {
            return null;
        }

        if (str_contains($certificateBytes, '-----BEGIN CERTIFICATE-----')) {
            return $certificateBytes;
        }

        $pem = "-----BEGIN CERTIFICATE-----\n".
            chunk_split(base64_encode($certificateBytes), 64, "\n").
            "-----END CERTIFICATE-----\n";

        if (@openssl_x509_read($pem) === false) {
            return null;
        }

        return $pem;
    }
}

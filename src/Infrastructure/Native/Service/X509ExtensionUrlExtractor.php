<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Service;

final class X509ExtensionUrlExtractor
{
    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    public function ocspUrls(array $parsed): array
    {
        $aia = (string) (($parsed['extensions']['authorityInfoAccess'] ?? ''));
        if ($aia === '') {
            return [];
        }

        preg_match_all('/OCSP\s*-\s*URI:([^\s,\n]+)/i', $aia, $matches);

        return $this->normalizeUrls($matches[1] ?? []);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    public function crlUrls(array $parsed): array
    {
        $cdp = (string) (($parsed['extensions']['crlDistributionPoints'] ?? ''));
        if ($cdp === '') {
            return [];
        }

        preg_match_all('/URI:([^\s,\n]+)/i', $cdp, $matches);

        return $this->normalizeUrls($matches[1] ?? []);
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    private function normalizeUrls(array $urls): array
    {
        $unique = [];
        foreach ($urls as $url) {
            $url = trim($url);
            if ($url === '' || ! preg_match('#^https?://#i', $url)) {
                continue;
            }

            $unique[$url] = true;
        }

        return array_keys($unique);
    }
}

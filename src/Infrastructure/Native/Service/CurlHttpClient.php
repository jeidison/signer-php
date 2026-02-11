<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Service;

use PdfSigner\Infrastructure\Native\Contract\HttpClientInterface;
use PdfSigner\Infrastructure\Native\ValueObject\HttpResponse;

final class CurlHttpClient implements HttpClientInterface
{
    public function request(
        string $method,
        string $url,
        array $headers = [],
        string $body = '',
        int $timeoutSeconds = 10,
        bool $followRedirects = false,
    ): HttpResponse {
        $ch = curl_init($url);
        if ($ch === false) {
            return new HttpResponse(0, '', 'curl_init failed');
        }

        $method = strtoupper(trim($method));
        $isPost = $method === 'POST';

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_DNS_CACHE_TIMEOUT => 300,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POST => $isPost,
        ]);

        if ($isPost) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = $errno !== 0 ? curl_error($ch) : null;
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return new HttpResponse($status, is_string($raw) ? $raw : '', $error);
    }
}

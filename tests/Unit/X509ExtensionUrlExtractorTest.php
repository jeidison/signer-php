<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\Native\Service\X509ExtensionUrlExtractor;

final class X509ExtensionUrlExtractorTest extends TestCase
{
    public function test_ocsp_urls_extracts_and_normalizes_http_urls(): void
    {
        $extractor = new X509ExtensionUrlExtractor;

        $parsed = [
            'extensions' => [
                'authorityInfoAccess' => implode("\n", [
                    'OCSP - URI:http://ocsp.example.com',
                    'ocsp - uri:https://ocsp.example.com/v2',
                    'OCSP - URI:ldap://ignored.example.com',
                    'OCSP - URI:http://ocsp.example.com',
                ]),
            ],
        ];

        self::assertSame(
            ['http://ocsp.example.com', 'https://ocsp.example.com/v2'],
            $extractor->ocspUrls($parsed),
        );
    }

    public function test_crl_urls_extracts_and_ignores_non_http_urls(): void
    {
        $extractor = new X509ExtensionUrlExtractor;

        $parsed = [
            'extensions' => [
                'crlDistributionPoints' => implode("\n", [
                    'URI:http://crl.example.com/a.crl',
                    'URI:https://crl.example.com/b.crl',
                    'URI:ftp://ignored.example.com/c.crl',
                    'URI:https://crl.example.com/b.crl',
                ]),
            ],
        ];

        self::assertSame(
            ['http://crl.example.com/a.crl', 'https://crl.example.com/b.crl'],
            $extractor->crlUrls($parsed),
        );
    }

    public function test_ocsp_urls_returns_empty_array_when_extension_is_missing(): void
    {
        $extractor = new X509ExtensionUrlExtractor;

        self::assertSame([], $extractor->ocspUrls([]));
    }

    public function test_crl_urls_returns_empty_array_when_extension_is_missing(): void
    {
        $extractor = new X509ExtensionUrlExtractor;

        self::assertSame([], $extractor->crlUrls([]));
    }
}

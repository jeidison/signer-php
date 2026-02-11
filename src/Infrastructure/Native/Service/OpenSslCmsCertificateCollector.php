<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\Service;

use SignerPHP\Infrastructure\Native\Contract\ProcessRunnerInterface;
use SignerPHP\Infrastructure\Native\Contract\SignatureCertificateCollectorInterface;

final class OpenSslCmsCertificateCollector implements SignatureCertificateCollectorInterface
{
    public function __construct(
        private readonly ProcessRunnerInterface $processRunner = new ShellProcessRunner,
    ) {}

    public function collectDerCertificates(string $signatureHex): array
    {
        $hex = strtoupper(trim($signatureHex));
        if ($hex === '' || preg_match('/\A[0]+\z/', $hex) === 1) {
            return [];
        }

        // Signature slots are padded with zeros; remove trailing null bytes represented as 00.
        $hex = preg_replace('/(?:00)+$/', '', $hex) ?? $hex;
        if ($hex === '' || (strlen($hex) % 2) !== 0 || ! ctype_xdigit($hex)) {
            return [];
        }

        $der = hex2bin($hex);
        if ($der === false || $der === '') {
            return [];
        }

        $tmpDir = sys_get_temp_dir();
        $cmsFile = tempnam($tmpDir, 'pdfsig-cms');
        $pemOut = tempnam($tmpDir, 'pdfsig-certs');
        if ($cmsFile === false || $pemOut === false) {
            return [];
        }

        file_put_contents($cmsFile, $der);

        try {
            $command = sprintf(
                'openssl pkcs7 -inform DER -in %s -print_certs -out %s',
                escapeshellarg($cmsFile),
                escapeshellarg($pemOut),
            );

            $result = $this->processRunner->run($command);
            if (! $result->succeeded()) {
                return [];
            }

            $pem = file_get_contents($pemOut);
            if ($pem === false || $pem === '') {
                return [];
            }

            preg_match_all('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $pem, $matches);
            $blocks = $matches[1] ?? [];
            $certs = [];

            foreach ($blocks as $block) {
                $base64 = preg_replace('/\s+/', '', $block) ?? '';
                if ($base64 === '') {
                    continue;
                }

                $certDer = base64_decode($base64, true);
                if ($certDer === false || $certDer === '') {
                    continue;
                }

                $certs[] = $certDer;
            }

            return $certs;
        } finally {
            @unlink($cmsFile);
            @unlink($pemOut);
        }
    }
}

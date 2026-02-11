<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Contract;

interface SignatureCertificateCollectorInterface
{
    /**
     * @return array<int, string> DER-encoded certificate binaries
     */
    public function collectDerCertificates(string $signatureHex): array;
}

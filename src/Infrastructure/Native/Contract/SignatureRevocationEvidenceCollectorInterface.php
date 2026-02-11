<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\Native\Contract;

interface SignatureRevocationEvidenceCollectorInterface
{
    /**
     * @param  array<int, string>  $certificateChainDer
     * @return array<int, array{ocsp:array<int,string>,crl:array<int,string>}>
     */
    public function collect(array $certificateChainDer): array;
}

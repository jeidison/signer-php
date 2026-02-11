<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final readonly class BrazilPolicyLpaUrlsDto
{
    public function __construct(
        public string $lpaUrlAsn1Pades = 'https://politicas.icpbrasil.gov.br/LPA_PAdES.der',
        public string $lpaUrlAsn1SignaturePades = 'https://politicas.icpbrasil.gov.br/LPA_PAdES.p7s',
    ) {}
}

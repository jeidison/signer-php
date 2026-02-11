<?php

declare(strict_types=1);

namespace PdfSigner\Application\DTO;

final readonly class BrazilTrustAnchorsOptionsDto
{
    /**
     * @param  array<int, string>  $urls
     */
    public function __construct(
        public ?string $directory = null,
        public array $urls = self::DEFAULT_URLS,
    ) {}

    /**
     * @var array<int, string>
     */
    public const DEFAULT_URLS = [
        'http://acraiz.icpbrasil.gov.br/Certificado_AC_Raiz.crt',
        'http://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv2.crt',
        'http://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv5.crt',
        'http://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv6.crt',
        'http://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv7.crt',
    ];

    public static function defaults(): self
    {
        return new self(
            directory: rtrim(sys_get_temp_dir(), '/').'/signer-php/trust-anchors',
            urls: self::DEFAULT_URLS,
        );
    }
}

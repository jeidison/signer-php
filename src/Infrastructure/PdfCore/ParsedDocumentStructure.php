<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore;

use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValue;

final readonly class ParsedDocumentStructure
{
    /**
     * @param  array<int, int|array{stmoid:int,pos:int}|null>  $xrefTable
     * @param  array<int, int>  $revisions
     */
    public function __construct(
        public ?PDFValue $trailer,
        public string $version,
        public array $xrefTable,
        public int $xrefPosition,
        public string $xrefVersion,
        public array $revisions,
    ) {}

    /**
     * @return array{trailer:PDFValue|null,version:string,xref:array<int, int|array{stmoid:int,pos:int}|null>,xrefposition:int,xrefversion:string,revisions:array<int,int>}
     */
    public function toArray(): array
    {
        return [
            'trailer' => $this->trailer,
            'version' => $this->version,
            'xref' => $this->xrefTable,
            'xrefposition' => $this->xrefPosition,
            'xrefversion' => $this->xrefVersion,
            'revisions' => $this->revisions,
        ];
    }
}

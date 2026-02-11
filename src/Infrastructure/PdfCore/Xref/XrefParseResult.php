<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore\Xref;

use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValue;

final readonly class XrefParseResult
{
    /**
     * @param  array<int, int|array{stmoid:int,pos:int}|null>  $table
     */
    public function __construct(
        public array $table,
        public PDFValue $trailer,
        public string $minimumPdfVersion,
    ) {}

    /**
     * @return array{0:array<int, int|array{stmoid:int,pos:int}|null>,1:PDFValue,2:string}
     */
    public function toLegacyTuple(): array
    {
        return [$this->table, $this->trailer, $this->minimumPdfVersion];
    }

    /**
     * @return array{0:array<int, int|array{stmoid:int,pos:int}|null>,1:PDFValue,2:string}
     */
    public function toLegacyXrefTuple(): array
    {
        return $this->toLegacyTuple();
    }
}

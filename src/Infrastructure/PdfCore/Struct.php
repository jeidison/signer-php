<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore;

use Exception;
use SignerPHP\Infrastructure\PdfCore\Xref\CrossReferenceManager;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 **/
class Struct
{
    private PdfDocument $pdfDocument;

    private string $separator = "\r\n";

    private const REGEX_PDF_VERSION = '/^%PDF-\d+\.\d+$/';

    public static function new(): static
    {
        return new static;
    }

    public function withPdfDocument(PdfDocument $pdfDocument): self
    {
        $this->pdfDocument = $pdfDocument;

        return $this;
    }

    /**
     * @return array{trailer:\SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValue|null,version:string,xref:array<int, int|array{stmoid:int,pos:int}|null>,xrefposition:int,xrefversion:string,revisions:array<int,int>}
     */
    public function structure(): array
    {
        return $this->parse()->toArray();
    }

    public function parse(): ParsedDocumentStructure
    {
        $pdfVersion = strtok($this->pdfDocument->getBuffer()->raw(), $this->separator);
        if ($pdfVersion === false) {
            throw new Exception('Failed to get PDF version');
        }

        if (preg_match(self::REGEX_PDF_VERSION, $pdfVersion, $matches) !== 1) {
            throw new Exception('PDF version not found');
        }

        preg_match_all('/startxref\s*([0-9]+)\s*%%EOF($|[\r\n])/ms', $this->pdfDocument->getBuffer()->raw(), $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $versions = [];
        foreach ($matches as $match) {
            $versions[] = intval($match[2][1]) + strlen($match[2][0]);
        }

        $startXRefPos = strrpos($this->pdfDocument->getBuffer()->raw(), 'startxref');
        if ($startXRefPos === false) {
            throw new Exception('startxref not found');
        }

        if (preg_match('/startxref\s*([0-9]+)\s*%%EOF\s*$/ms', $this->pdfDocument->getBuffer()->raw(), $matches, 0, $startXRefPos) !== 1) {
            throw new Exception('startxref and %%EOF not found');
        }

        $xrefPos = intval($matches[1]);

        if ($xrefPos === 0) {
            return new ParsedDocumentStructure(
                trailer: null,
                version: substr($pdfVersion, 1),
                xrefTable: [],
                xrefPosition: 0,
                xrefVersion: substr($pdfVersion, 1),
                revisions: $versions,
            );
        }

        $xref = CrossReferenceManager::new()
            ->withXrefPosition($xrefPos)
            ->withPdfDocument($this->pdfDocument)
            ->parse();

        return new ParsedDocumentStructure(
            trailer: $xref->trailer,
            version: substr($pdfVersion, 1),
            xrefTable: $xref->table,
            xrefPosition: $xrefPos,
            xrefVersion: $xref->minimumPdfVersion,
            revisions: $versions,
        );
    }
}

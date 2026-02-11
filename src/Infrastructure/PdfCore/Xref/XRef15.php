<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore\Xref;

use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\Xref\Service\XRef15Parser;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 **/
class XRef15
{
    private PdfDocument $pdfDocument;

    private int $xrefPosition;

    private ?XRef15Parser $parser = null;

    public static function new(): static
    {
        return new static;
    }

    public function withPdfDocument(PdfDocument $pdfDocument): self
    {
        $this->pdfDocument = $pdfDocument;

        return $this;
    }

    public function withXrefPosition(?int $xrefPos): self
    {
        $this->xrefPosition = $xrefPos;

        return $this;
    }

    public function parse(): XrefParseResult
    {
        return $this->parser()->parse($this->pdfDocument, $this->xrefPosition);
    }

    public function toLegacyTuple(): array
    {
        return $this->parse()->toLegacyTuple();
    }

    private function parser(): XRef15Parser
    {
        $this->parser ??= new XRef15Parser;

        return $this->parser;
    }
}

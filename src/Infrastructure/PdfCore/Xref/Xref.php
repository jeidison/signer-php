<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Xref;

use SignerPHP\Infrastructure\PdfCore\Buffer;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\Xref\Service\XrefContentBuilder;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 **/
class Xref
{
    private PdfDocument $pdfDocument;

    private int $xrefPosition;

    private ?XrefContentBuilder $contentBuilder = null;

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
        if ($this->isCrossReferenceStream()) {
            return XRef15::new()
                ->withPdfDocument($this->pdfDocument)
                ->withXrefPosition($this->xrefPosition)
                ->parse();
        }

        return XRef14::new()
            ->withBuffer($this->pdfDocument->getBuffer()->raw())
            ->withXrefPosition($this->xrefPosition)
            ->parse();
    }

    public function toLegacyTuple(): array
    {
        return $this->parse()->toLegacyTuple();
    }

    public function buildXref15(array $offsets): array
    {
        return $this->contentBuilder()->buildXref15($offsets);
    }

    public function buildXref(array $offsets): string
    {
        return $this->contentBuilder()->buildXref14($offsets);
    }

    public function generateContentToXref(): array
    {
        $result = new Buffer($this->pdfDocument->getBuffer()->raw());
        $offsets = [];
        $offsets[0] = 0;

        $offset = $result->size();
        foreach ($this->pdfDocument->getPdfObjects() as $objId => $object) {
            $result->data($object->toPdfEntry());
            $offsets[$objId] = $offset;
            $offset = $result->size();
        }

        return [$result, $offsets];
    }

    private function isCrossReferenceStream(): bool
    {
        $raw = $this->pdfDocument->getBuffer()->raw();
        if ($this->xrefPosition > strlen($raw)) {
            return false;
        }

        // Look at what is actually at the xref position.
        // Skip whitespace, then check whether we see an object header (N G obj) — which
        // indicates a cross-reference stream — or the literal 'xref' keyword.
        $snippet = substr($raw, $this->xrefPosition, 64);
        $trimmed = ltrim($snippet);

        // If trimmed content looks like an object definition (digits … obj), it's a stream.
        if (preg_match('/^\d+\s+\d+\s+obj\b/', $trimmed) === 1) {
            return true;
        }

        // If trimmed content starts with 'xref', it's a cross-reference table.
        if (strncmp($trimmed, 'xref', 4) === 0) {
            return false;
        }

        // Fallback: use the old heuristic (no 'trailer' keyword after this position → stream).
        return strpos($raw, 'trailer', $this->xrefPosition) === false;
    }

    private function contentBuilder(): XrefContentBuilder
    {
        $this->contentBuilder ??= new XrefContentBuilder;

        return $this->contentBuilder;
    }
}

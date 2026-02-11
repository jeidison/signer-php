<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore\Xref;

use PdfSigner\Infrastructure\PdfCore\Buffer;
use PdfSigner\Infrastructure\PdfCore\PdfDocument;
use PdfSigner\Infrastructure\PdfCore\Xref\Service\XrefContentBuilder;

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
        return strpos($this->pdfDocument->getBuffer()->raw(), 'trailer', $this->xrefPosition) === false;
    }

    private function contentBuilder(): XrefContentBuilder
    {
        $this->contentBuilder ??= new XrefContentBuilder;

        return $this->contentBuilder;
    }
}

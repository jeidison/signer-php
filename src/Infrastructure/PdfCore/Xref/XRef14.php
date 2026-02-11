<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore\Xref;

use PdfSigner\Infrastructure\PdfCore\Xref\Service\XRef14Parser;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 **/
class XRef14
{
    private string $buffer;

    private int $xrefPosition;

    private ?XRef14Parser $parser = null;

    public static function new(): static
    {
        return new static;
    }

    public function withBuffer(string $buffer): self
    {
        $this->buffer = $buffer;

        return $this;
    }

    public function withXrefPosition(?int $xrefPos): self
    {
        $this->xrefPosition = $xrefPos;

        return $this;
    }

    public function parse(): XrefParseResult
    {
        return $this->parser()->parse($this->buffer, $this->xrefPosition);
    }

    public function toLegacyTuple(): array
    {
        return $this->parse()->toLegacyTuple();
    }

    private function parser(): XRef14Parser
    {
        $this->parser ??= new XRef14Parser;

        return $this->parser;
    }
}

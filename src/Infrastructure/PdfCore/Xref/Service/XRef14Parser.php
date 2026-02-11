<?php

declare(strict_types=1);

namespace PdfSigner\Infrastructure\PdfCore\Xref\Service;

use PdfSigner\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
use PdfSigner\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValue;
use PdfSigner\Infrastructure\PdfCore\Trailer;
use PdfSigner\Infrastructure\PdfCore\Xref\XrefParseResult;

final class XRef14Parser
{
    public function parse(string $buffer, int $xrefPosition): XrefParseResult
    {
        $trailerPosition = strpos($buffer, 'trailer', $xrefPosition);
        if ($trailerPosition === false) {
            throw new PdfCoreParsingException('Trailer tag not found after xref at position '.$xrefPosition);
        }

        $version = '1.4';
        $xrefText = substr($buffer, $xrefPosition, $trailerPosition - $xrefPosition);
        $xrefTable = $this->parseEntries($xrefText, $xrefPosition);

        $trailer = Trailer::new()
            ->withBuffer($buffer)
            ->withTrailerPosition($trailerPosition)
            ->getTrailer();

        if (isset($trailer['Prev'])) {
            $xrefTable = $this->mergePreviousTables($buffer, $trailer, $version, $xrefTable);
        }

        return new XrefParseResult($xrefTable, $trailer, $version);
    }

    /**
     * @return array<int, int|array{stmoid:int,pos:int}|null>
     */
    private function parseEntries(string $xrefText, int $xrefPosition): array
    {
        $lineSeparator = "\r\n";
        $line = strtok($xrefText, $lineSeparator);
        if ($line !== 'xref') {
            throw new PdfCoreParsingException('Xref tag not found at position '.$xrefPosition);
        }

        $currentObjectId = 0;
        $remainingObjectsInSection = 0;
        $xrefTable = [];

        while (($line = strtok($lineSeparator)) !== false) {
            if (preg_match('/(\d+) (\d+)$/', $line, $matches) === 1) {
                if ($remainingObjectsInSection > 0) {
                    throw new PdfCoreParsingException('Malformed xref at position '.$xrefPosition);
                }

                $currentObjectId = (int) $matches[1];
                $remainingObjectsInSection = (int) $matches[2];

                continue;
            }

            if (preg_match('/^(\d+) (\d+) (.)\s*/', $line, $matches) !== 1) {
                continue;
            }

            if ($remainingObjectsInSection === 0) {
                throw new PdfCoreParsingException('Unexpected entry for xref: '.$line);
            }

            $this->applyEntry($xrefTable, $currentObjectId, (int) $matches[1], (int) $matches[2], $matches[3]);
            $currentObjectId++;
            $remainingObjectsInSection--;
        }

        return $xrefTable;
    }

    /**
     * @param  array<int, int|array{stmoid:int,pos:int}|null>  $xrefTable
     */
    private function applyEntry(array &$xrefTable, int $objectId, int $offset, int $generation, string $operation): void
    {
        if ($offset === 0) {
            return;
        }

        if ($operation === 'f') {
            $xrefTable[$objectId] = null;

            return;
        }

        if ($operation === 'n') {
            if ($generation !== 0) {
                throw new PdfCoreStructureException('Objects of non-zero generation are not supported.');
            }

            $xrefTable[$objectId] = $offset;
        }
    }

    /**
     * @param  array<int, int|array{stmoid:int,pos:int}|null>  $currentTable
     * @return array<int, int|array{stmoid:int,pos:int}|null>
     */
    private function mergePreviousTables(string $buffer, PDFValue $trailer, string $version, array $currentTable): array
    {
        $prev = $trailer['Prev'] ?? null;
        $prevPosition = $prev->val();
        if (! is_numeric($prevPosition)) {
            throw new PdfCoreStructureException('Invalid trailer: Prev must be numeric.');
        }

        $previous = $this->parse($buffer, (int) $prevPosition);

        foreach ($previous->table as $objectId => $offset) {
            if (! isset($currentTable[$objectId])) {
                $currentTable[$objectId] = $offset;
            }
        }

        return $currentTable;
    }
}

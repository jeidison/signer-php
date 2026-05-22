<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Xref\Service;

use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValue;
use SignerPHP\Infrastructure\PdfCore\Trailer;
use SignerPHP\Infrastructure\PdfCore\Xref\XrefParseResult;

final class XRef14Parser
{
    /**
     * @throws PdfCoreParsingException
     * @throws PdfCoreStructureException
     */
    public function parse(string $buffer, int $xrefPosition, array $visitedPositions = []): XrefParseResult
    {
        if ($xrefPosition > strlen($buffer)) {
            throw new PdfCoreParsingException('Xref position '.$xrefPosition.' is beyond end of file');
        }

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

        $visitedPositions[$xrefPosition] = true;

        if (isset($trailer['Prev'])) {
            $xrefTable = $this->mergePreviousTables($buffer, $trailer, $version, $xrefTable, $visitedPositions);
        }

        return new XrefParseResult($xrefTable, $trailer, $version);
    }

    /**
     * @return array<int, int|array{stmoid:int,pos:int}|null>
     *
     * @throws PdfCoreParsingException
     * @throws PdfCoreStructureException
     */
    private function parseEntries(string $xrefText, int $xrefPosition): array
    {
        // Scan forward to find the 'xref' keyword — some generators have startxref pointing
        // slightly before it (e.g. into trailing 'endobj'), or 'xref' may share a line with
        // preceding tokens like 'endobj xref'.
        $xrefKeywordOffset = false;
        $searchOffset = 0;
        while (($pos = strpos($xrefText, 'xref', $searchOffset)) !== false) {
            // Verify 'xref' is followed by whitespace or end of string (not part of a longer word).
            $after = $pos + 4;
            if ($after >= strlen($xrefText) || ctype_space($xrefText[$after])) {
                $xrefKeywordOffset = $pos;
                break;
            }
            $searchOffset = $pos + 1;
        }

        if ($xrefKeywordOffset === false) {
            throw new PdfCoreParsingException('Xref tag not found at position '.$xrefPosition);
        }

        // Use only the part of xrefText starting after the 'xref' keyword.
        $xrefText = substr($xrefText, $xrefKeywordOffset + 4);
        $lineSeparator = "\r\n";

        $currentObjectId = 0;
        $remainingObjectsInSection = 0;
        $xrefTable = [];
        $sawSectionHeader = false;
        $parsedEntries = 0;

        // Initialize strtok; use a for-loop pattern to avoid strtok re-initialization issues.
        for ($line = strtok($xrefText, $lineSeparator); $line !== false; $line = strtok($lineSeparator)) {
            if (preg_match('/^\s*(\d+)\s+(\d+)\s*$/', $line, $matches) === 1) {
                // Start a new section. If the previous section still had declared entries
                // outstanding, accept the mismatch (common in non-conforming generators).
                $sawSectionHeader = true;
                $currentObjectId = (int) $matches[1];
                $remainingObjectsInSection = (int) $matches[2];

                continue;
            }

            if (preg_match('/^(\d+) (\d+) (.)\s*/', $line, $matches) !== 1) {
                continue;
            }

            if ($remainingObjectsInSection === 0) {
                // Entry appears outside any declared section; skip tolerantly.
                continue;
            }

            $this->applyEntry($xrefTable, $currentObjectId, (int) $matches[1], (int) $matches[2], $matches[3]);
            $parsedEntries++;
            $currentObjectId++;
            $remainingObjectsInSection--;
        }

        // An empty xref (0 0 section header, no entries) is valid for incremental updates.
        if (! $sawSectionHeader) {
            throw new PdfCoreParsingException('Malformed xref at position '.$xrefPosition);
        }

        return $xrefTable;
    }

    /**
     * @param  array<int, int|array{stmoid:int,pos:int}|null>  $xrefTable
     *
     * @throws PdfCoreStructureException
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
            // Non-zero generations indicate reused object slots (after free/reuse cycles).
            // We store the entry by offset; the generation field is not used by the signer.
            $xrefTable[$objectId] = $offset;
        }
    }

    /**
     * @param  array<int, int|array{stmoid:int,pos:int}|null>  $currentTable
     * @return array<int, int|array{stmoid:int,pos:int}|null>
     *
     * @throws PdfCoreParsingException
     * @throws PdfCoreStructureException
     */
    private function mergePreviousTables(string $buffer, PDFValue $trailer, string $version, array $currentTable, array $visitedPositions = []): array
    {
        $prev = $trailer['Prev'] ?? null;
        $prevPosition = $prev->val();
        if (! is_numeric($prevPosition)) {
            throw new PdfCoreStructureException('Invalid trailer: Prev must be numeric.');
        }

        if (isset($visitedPositions[(int) $prevPosition])) {
            // Circular Prev chain detected — stop recursion.
            return $currentTable;
        }

        $previous = $this->parse($buffer, (int) $prevPosition, $visitedPositions);

        foreach ($previous->table as $objectId => $offset) {
            if (! isset($currentTable[$objectId])) {
                $currentTable[$objectId] = $offset;
            }
        }

        return $currentTable;
    }
}

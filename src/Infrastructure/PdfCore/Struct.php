<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore;

use Exception;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValue;
use SignerPHP\Infrastructure\PdfCore\Xref\CrossReferenceManager;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 **/
class Struct
{
    private PdfDocument $pdfDocument;

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

    /**
     * Parse PDF structure (version, cross-references, trailer).
     *
     * ISO 32000 §7.5.2 places the version marker `%PDF-x.y` at file start, but some tools
     * prepend UTF-8 BOM or binary bytes. Robustness principle (RFC 1122): scan first 8192
     * bytes for `%PDF-` pattern instead of strict first-line matching. Consistent with
     * libpoppler, PDFium, Apache PDFBox.
     */
    public function parse(): ParsedDocumentStructure
    {
        $buffer = $this->pdfDocument->getBuffer()->raw();
        if ($buffer === '') {
            throw new Exception('Failed to get PDF version');
        }

        $pdfVersion = $this->resolvePdfVersion($buffer);
        if ($pdfVersion === null) {
            throw new Exception('PDF version not found');
        }

        preg_match_all('/startxref\s*([0-9]+)\s*%%EOF($|[\r\n])/ms', $buffer, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $versions = [];
        foreach ($matches as $match) {
            $versions[] = intval($match[2][1]) + strlen($match[2][0]);
        }

        $xrefPos = $this->resolveXrefPosition($buffer);

        if ($xrefPos === null) {
            $recovered = $this->recoverStructureWithoutXref($buffer, $pdfVersion, $versions);
            if ($recovered !== null) {
                return $recovered;
            }

            throw new Exception('startxref not found');
        }

        if ($xrefPos === 0) {
            return new ParsedDocumentStructure(
                trailer: null,
                version: $pdfVersion,
                xrefTable: [],
                xrefPosition: 0,
                xrefVersion: $pdfVersion,
                revisions: $versions,
            );
        }

        try {
            $xref = CrossReferenceManager::new()
                ->withXrefPosition($xrefPos)
                ->withPdfDocument($this->pdfDocument)
                ->parse();
        } catch (Exception $e) {
            $recovered = $this->recoverStructureWithoutXref($buffer, $pdfVersion, $versions);
            if ($recovered !== null) {
                return $recovered;
            }

            throw $e;
        }

        return new ParsedDocumentStructure(
            trailer: $xref->trailer,
            version: $pdfVersion,
            xrefTable: $xref->table,
            xrefPosition: $xrefPos,
            xrefVersion: $xref->minimumPdfVersion,
            revisions: $versions,
        );
    }

    /**
     * @param  array<int, int>  $versions
     */
    private function recoverStructureWithoutXref(string $buffer, string $pdfVersion, array $versions): ?ParsedDocumentStructure
    {
        $trailer = $this->extractLastTrailerObject($buffer);
        if ($trailer === null) {
            $trailer = $this->extractSyntheticTrailerFromRootReference($buffer);
        }

        if ($trailer === null) {
            return null;
        }

        $xrefTable = $this->buildSyntheticXrefTableFromObjectHeaders($buffer);
        if ($xrefTable === []) {
            return null;
        }

        return new ParsedDocumentStructure(
            trailer: $trailer,
            version: $pdfVersion,
            xrefTable: $xrefTable,
            xrefPosition: 0,
            xrefVersion: $pdfVersion,
            revisions: $versions,
        );
    }

    /**
     * Resolve the xref table position from the PDF buffer.
     *
     * Strategy (in order):
     *  1. Parse `startxref <offset> %%EOF` (ISO 32000 §7.5.5, strict form).
     *  2. Parse `startxref <offset>` without `%%EOF` (lenient -- handles truncated trailers).
     *  3. Scan backward from EOF for the last standalone `xref` keyword
     *     (handles PDFs where `startxref` is absent or carries no valid offset).
     *
     * Returns null only when all three strategies yield nothing, allowing callers
     * to throw a meaningful exception.
     */
    private function resolveXrefPosition(string $buffer): ?int
    {
        $startXRefPos = strrpos($buffer, 'startxref');

        if ($startXRefPos !== false) {
            // Strict form: startxref <offset> %%EOF
            if (preg_match('/startxref\s*([0-9]+)\s*%%EOF\s*$/ms', $buffer, $matches, 0, $startXRefPos) === 1) {
                return intval($matches[1]);
            }

            // Lenient form: startxref <offset> (no %%EOF -- truncated or malformed trailer)
            if (preg_match('/startxref\s*([0-9]+)/ms', $buffer, $matches, 0, $startXRefPos) === 1) {
                return intval($matches[1]);
            }
        }

        // Last resort: no valid startxref offset found. Scan backward for the last
        // standalone 'xref' keyword so we can locate the xref table directly.
        $xrefTablePosition = $this->findLastStandaloneXref($buffer);
        if ($xrefTablePosition !== null) {
            return $xrefTablePosition;
        }

        // Some malformed PDFs omit startxref entirely but still contain a valid xref stream.
        return $this->findLastXrefStreamObjectPosition($buffer);
    }

    /**
     * Scan backward from EOF for the last standalone `xref` keyword.
     *
     * `xref` may appear on its own line or after a non-letter separator on the
     * same line (e.g. `endobj xref`). We intentionally reject matches embedded
     * in alphabetic tokens such as `startxref`, and we keep only candidates
     * that are followed by a `trailer` section.
     */
    private function findLastStandaloneXref(string $buffer): ?int
    {
        if (preg_match_all('/(?:^|[\r\n \t])(xref)(?:\r\n|\n)/m', $buffer, $matches, PREG_OFFSET_CAPTURE) > 0) {
            $xrefMatches = $matches[1] ?? [];
            for ($i = count($xrefMatches) - 1; $i >= 0; $i--) {
                $candidate = $xrefMatches[$i];
                    if (! is_array($candidate)) {
                        continue;
                    }
                    $xrefOffset = (int) ($candidate[1] ?? 0);
                if (strpos($buffer, 'trailer', $xrefOffset) !== false) {
                    return $xrefOffset;
                }
            }
        }

        return null;
    }

    /**
     * Find the byte offset of the last object header whose dictionary declares /Type /XRef.
     * Returns null when no xref stream object can be identified.
     */
    private function findLastXrefStreamObjectPosition(string $buffer): ?int
    {
        $scanOffset = 0;
        $lastObjectOffset = null;

        while (preg_match('/\/Type\s*\/XRef\b/', $buffer, $typeMatch, PREG_OFFSET_CAPTURE, $scanOffset) === 1) {
            $typeOffset = $typeMatch[0][1];
            $windowStart = max(0, $typeOffset - 4096);
            $window = substr($buffer, $windowStart, $typeOffset - $windowStart);

            if (preg_match_all('/\d+\s+\d+\s+obj\b/', $window, $objectMatches, PREG_OFFSET_CAPTURE) > 0) {
                $lastObject = end($objectMatches[0]);
                $lastObjectOffset = $windowStart + $lastObject[1];
            }

            $scanOffset = $typeOffset + 1;
        }

        return $lastObjectOffset;
    }

    private function extractLastTrailerObject(string $buffer): ?PDFValue
    {
        $trailerPos = strrpos($buffer, 'trailer');
        if ($trailerPos === false) {
            return null;
        }

        try {
            return (new ObjectParser)->parseString($buffer, $trailerPos + strlen('trailer'));
        } catch (Exception) {
            return null;
        }
    }

    private function extractSyntheticTrailerFromRootReference(string $buffer): ?PDFValue
    {
        if (preg_match_all('/\/Root\s+([^\s]+)\s+([^\s]+)\s+R\b/', $buffer, $matches, PREG_SET_ORDER) === 0) {
            return null;
        }

        $lastRootReference = end($matches);
        if ($lastRootReference === false) {
            return null;
        }

        $rootOidRaw = (string) $lastRootReference[1];
        $rootGenerationRaw = (string) $lastRootReference[2];

        $rootOid = $this->parsePositiveIntWithinRange($rootOidRaw);
        $rootGeneration = $this->parsePositiveIntWithinRange($rootGenerationRaw);
        if ($rootOid === null || $rootGeneration === null || $rootOid === 0) {
            return null;
        }

        $syntheticTrailer = sprintf('<< /Root %d %d R >>', $rootOid, $rootGeneration);

        try {
            return (new ObjectParser)->parseString($syntheticTrailer);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Build a synthetic xref table from object headers for malformed files that
     * contain objects and trailer, but no xref/startxref section.
     *
     * @return array<int, int|array{stmoid:int,pos:int}|null>
     */
    private function buildSyntheticXrefTableFromObjectHeaders(string $buffer): array
    {
        if (preg_match_all('/(?:^|[\r\n])(\d+)\s+(\d+)\s+obj\b/m', $buffer, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        $xrefTable = [];
        $metadata = [];

        foreach ($matches as $match) {
            $oidRaw = (string) $match[1][0];
            $oidOffset = (int) $match[1][1];
            $generationRaw = (string) $match[2][0];

            $oid = $this->parsePositiveIntWithinRange($oidRaw);
            $generation = $this->parsePositiveIntWithinRange($generationRaw);
            if ($oid === null || $generation === null || $oid === 0) {
                continue;
            }

            $current = $metadata[$oid] ?? null;
            if (! is_array($current)
                || $generation > $current['generation']
                || ($generation === $current['generation'] && $oidOffset > $current['offset'])) {
                $metadata[$oid] = ['generation' => $generation, 'offset' => $oidOffset];
                $xrefTable[$oid] = $oidOffset;
            }
        }

        ksort($xrefTable);

        return $xrefTable;
    }

    private function parsePositiveIntWithinRange(string $number): ?int
    {
        if ($number === '' || ! ctype_digit($number)) {
            return null;
        }

        $max = (string) PHP_INT_MAX;
        if (strlen($number) > strlen($max)) {
            return null;
        }

        if (strlen($number) === strlen($max) && strcmp($number, $max) > 0) {
            return null;
        }

        return (int) $number;
    }

    private function resolvePdfVersion(string $buffer): ?string
    {
        $headerWindow = substr($buffer, 0, 8192);
        if (preg_match('/%PDF-([^\s]+)/', $headerWindow, $matches) === 1) {
            $normalized = $this->normalizeMalformedPdfVersionToken((string) $matches[1]);
            if ($normalized !== null) {
                return 'PDF-'.$normalized;
            }
        }

        if ($this->looksLikePdfStructure($buffer)) {
            return 'PDF-1.4';
        }

        return null;
    }

    private function normalizeMalformedPdfVersionToken(string $token): ?string
    {
        if ($token === '') {
            return null;
        }

        if (preg_match('/^(\d+)\.(\d+)$/', $token, $matches) === 1) {
            return $matches[1].'.'.$matches[2];
        }

        if (preg_match('/^(\d+)\.$/', $token, $matches) === 1) {
            return $matches[1].'.0';
        }

        if (preg_match('/^[A-Za-z]\.([0-9]+)$/', $token, $matches) === 1) {
            return '1.'.$matches[1];
        }

        if (preg_match('/^(\d+)$/', $token, $matches) === 1) {
            return $matches[1].'.0';
        }

        return null;
    }

    private function looksLikePdfStructure(string $buffer): bool
    {
        if (preg_match('/\d+\s+\d+\s+obj\b/', $buffer) !== 1) {
            return false;
        }

        return str_contains($buffer, 'xref')
            || str_contains($buffer, 'trailer')
            || str_contains($buffer, '/Root');
    }
}

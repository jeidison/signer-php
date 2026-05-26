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

    private const REGEX_PDF_VERSION = '/%PDF-(\d+\.\d+)/';

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
     * prepend UTF-8 BOM or binary bytes. Robustness principle (RFC 1122): scan first 1024
     * bytes for `%PDF-` pattern instead of strict first-line matching. Consistent with
     * libpoppler, PDFium, Apache PDFBox.
     */
    public function parse(): ParsedDocumentStructure
    {
        $buffer = $this->pdfDocument->getBuffer()->raw();
        if ($buffer === '') {
            throw new Exception('Failed to get PDF version');
        }

        $headerWindow = substr($buffer, 0, 1024);
        if (preg_match(self::REGEX_PDF_VERSION, $headerWindow, $matches) !== 1) {
            throw new Exception('PDF version not found');
        }

        $pdfVersion = 'PDF-'.$matches[1];

        preg_match_all('/startxref\s*([0-9]+)\s*%%EOF($|[\r\n])/ms', $buffer, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $versions = [];
        foreach ($matches as $match) {
            $versions[] = intval($match[2][1]) + strlen($match[2][0]);
        }

        $xrefPos = $this->resolveXrefPosition($buffer);

        if ($xrefPos === null) {
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

        $xref = CrossReferenceManager::new()
            ->withXrefPosition($xrefPos)
            ->withPdfDocument($this->pdfDocument)
            ->parse();

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
     * Scan backward from EOF for the last standalone `xref` keyword (preceded by a newline).
     * Also handles `xref` at the very start of the buffer (no preceding newline).
     * Returns the byte offset of the `x` in `xref`, or null if not found.
     */
    private function findLastStandaloneXref(string $buffer): ?int
    {
        foreach (["\nxref\n", "\nxref\r\n", "\r\nxref\n", "\r\nxref\r\n"] as $needle) {
            $pos = strrpos($buffer, $needle);
            if ($pos !== false) {
                return $pos + 1; // skip the leading newline; point at 'x'
            }
        }

        // Also handle 'xref' at the very start of the buffer (no preceding newline).
        foreach (["xref\n", "xref\r\n"] as $needle) {
            if (str_starts_with($buffer, $needle)) {
                return 0;
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
}

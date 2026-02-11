<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Xref\Service;

use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\StreamReader;
use SignerPHP\Infrastructure\PdfCore\Xref\XrefParseResult;

final class XRef15Parser
{
    public function parse(PdfDocument $pdfDocument, int $xrefPosition): XrefParseResult
    {
        $xrefObject = $pdfDocument->objectFromString(null, $xrefPosition);

        if (! isset($xrefObject['Type']) || ($xrefObject['Type']->val() !== 'XRef')) {
            throw new PdfCoreStructureException('Invalid xref table');
        }

        $stream = $xrefObject->getStream(false);

        $fieldWidthsRaw = $xrefObject['W']->val(true);
        if (count($fieldWidthsRaw) !== 3) {
            throw new PdfCoreStructureException('Invalid cross reference object');
        }

        $fieldWidths = [(int) $fieldWidthsRaw[0], (int) $fieldWidthsRaw[1], (int) $fieldWidthsRaw[2]];

        $size = $xrefObject['Size']->asIntOrNull();
        if ($size === null) {
            throw new PdfCoreStructureException('Could not get the size of the xref table');
        }

        $indexRanges = [0, $size];
        if (isset($xrefObject['Index'])) {
            $indexRanges = $xrefObject['Index']->val(true);
        }

        if (count($indexRanges) % 2 !== 0) {
            throw new PdfCoreStructureException('Invalid indexes of xref table');
        }

        $xrefTable = [];
        if (isset($xrefObject['Prev'])) {
            $prev = $xrefObject['Prev']->asIntOrNull();
            if ($prev === null) {
                throw new PdfCoreStructureException('Invalid reference to a previous xref table');
            }

            $xrefTable = $this->parse($pdfDocument, $prev)->table;
        }

        $reader = new StreamReader($stream);
        $this->readEntries($reader, $fieldWidths, $indexRanges, $xrefTable);

        return new XrefParseResult($xrefTable, $xrefObject->getValue(), '1.5');
    }

    /**
     * @param  array<int,int>  $fieldWidths
     * @param  array<int,int>  $indexRanges
     * @param  array<int, int|array{stmoid:int,pos:int}|null>  $xrefTable
     */
    private function readEntries(StreamReader $reader, array $fieldWidths, array $indexRanges, array &$xrefTable): void
    {
        for ($i = 0; $i < count($indexRanges); $i += 2) {
            $objectId = (int) $indexRanges[$i];
            $objectsInRange = (int) $indexRanges[$i + 1];

            while (($reader->currentChar() !== false) && ($objectsInRange > 0)) {
                $entryType = $fieldWidths[0] !== 0 ? $this->decodeUnsignedInt($reader->nextChars($fieldWidths[0]), $fieldWidths[0]) : 1;
                $fieldTwo = $this->decodeUnsignedInt($reader->nextChars($fieldWidths[1]), $fieldWidths[1]);
                $fieldThree = $this->decodeUnsignedInt($reader->nextChars($fieldWidths[2]), $fieldWidths[2]);

                $this->assignEntry($xrefTable, $objectId, $entryType, $fieldTwo, $fieldThree);

                $objectId++;
                $objectsInRange--;
            }
        }
    }

    /**
     * @param  array<int, int|array{stmoid:int,pos:int}|null>  $xrefTable
     */
    private function assignEntry(array &$xrefTable, int $objectId, int $entryType, int $fieldTwo, int $fieldThree): void
    {
        switch ($entryType) {
            case 0:
                $xrefTable[$objectId] = null;

                return;
            case 1:
                if ($fieldThree !== 0) {
                    throw new PdfCoreStructureException('Objects of non-zero generation are not supported.');
                }
                $xrefTable[$objectId] = $fieldTwo;

                return;
            case 2:
                $xrefTable[$objectId] = ['stmoid' => $fieldTwo, 'pos' => $fieldThree];

                return;
            default:
                throw new PdfCoreParsingException('Invalid stream for xref table');
        }
    }

    private function decodeUnsignedInt(string $raw, int $byteWidth): int
    {
        if ($byteWidth < 0 || $byteWidth > 8) {
            throw new PdfCoreStructureException('Invalid field widths for cross reference stream.');
        }

        if ($byteWidth === 0) {
            return 0;
        }

        $raw = str_pad($raw, $byteWidth, chr(0), STR_PAD_LEFT);
        $value = 0;
        foreach (unpack('C*', $raw) as $byte) {
            $value = ($value << 8) | $byte;
        }

        return $value;
    }
}

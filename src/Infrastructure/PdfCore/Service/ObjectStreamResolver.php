<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Service;

use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValue;

final class ObjectStreamResolver
{
    public function resolveFromObjectStream(PdfDocument $pdfDocument, int $objstmOid, int $objpos, int $oid): PDFObject
    {
        $objstm = $pdfDocument->findObject($objstmOid);
        if ($objstm === null) {
            throw new PdfCoreStructureException('Could not resolve object stream '.$objstmOid);
        }

        if (($objstm['Extends'] ?? null) !== null) {
            throw new PdfCoreStructureException('Extended object streams are not supported.');
        }

        $first = $objstm['First'] ?? null;
        $n = $objstm['N'] ?? null;
        $type = $objstm['Type'] ?? null;

        if ($first === null || $n === null || $type === null) {
            throw new PdfCoreStructureException('Invalid object stream '.$objstmOid.'.');
        }

        if ($type->val() !== 'ObjStm') {
            throw new PdfCoreStructureException(sprintf('Object %s is not an object stream.', $objstmOid));
        }

        $firstValue = $first->asIntOrNull();
        if ($firstValue === null) {
            throw new PdfCoreStructureException('Invalid first object position in object stream '.$objstmOid);
        }

        $nValue = $n->asIntOrNull();
        if ($nValue === null || $nValue < 0) {
            throw new PdfCoreStructureException('Invalid object count in object stream '.$objstmOid);
        }

        $stream = $objstm->getStream(false);
        $index = substr((string) $stream, 0, $firstValue);
        preg_match_all('/-?\d+/', $index, $indexMatches);
        $fullIndex = $indexMatches[0] ?? [];
        $index = $fullIndex;
        $stream = substr((string) $stream, $firstValue);

        $expectedEntries = $nValue * 2;
        if ($expectedEntries > 0 && count($index) >= $expectedEntries) {
            $index = array_slice($index, 0, $expectedEntries);
        }

        if ((count($index) < 2 || count($index) % 2 !== 0) && count($fullIndex) >= 2 && count($fullIndex) % 2 === 0) {
            $index = $fullIndex;
        }

        if (count($index) < 2 || count($index) % 2 !== 0) {
            throw new PdfCoreParsingException('Invalid index for object stream '.$objstmOid);
        }

        $pairIndex = $objpos * 2;
        $offset = null;

        if ($pairIndex >= 0 && ($pairIndex + 1) < count($index)) {
            $indexedOid = (int) $index[$pairIndex];
            if ($indexedOid === $oid) {
                $offset = (int) $index[$pairIndex + 1];
            }
        }

        if ($offset === null) {
            $indexCount = count($index);
            for ($i = 0; $i + 1 < $indexCount; $i += 2) {
                if ((int) $index[$i] === $oid) {
                    $offset = (int) $index[$i + 1];
                    break;
                }
            }
        }

        if ($offset === null && $index !== $fullIndex && count($fullIndex) % 2 === 0) {
            $fullIndexCount = count($fullIndex);
            for ($i = 0; $i + 1 < $fullIndexCount; $i += 2) {
                if ((int) $fullIndex[$i] === $oid) {
                    $offset = (int) $fullIndex[$i + 1];
                    $index = $fullIndex;
                    break;
                }
            }
        }

        if ($offset === null) {
            throw new PdfCoreStructureException(sprintf('Object %s not found in object stream %s.', $oid, $objstmOid));
        }

        $offsets = [];
        $counter = count($index);
        for ($i = 1; $i < $counter; $i += 2) {
            $offsets[] = (int) $index[$i];
        }

        $offsets[] = strlen($stream);
        sort($offsets);

        $next = strlen($stream);
        foreach ($offsets as $candidate) {
            if ($candidate > $offset) {
                $next = $candidate;
                break;
            }
        }

        if ($offset < 0 || $offset > $next) {
            throw new PdfCoreParsingException('Invalid object offset inside object stream '.$objstmOid);
        }

        $objectDefStr = $oid.' 0 obj '.substr($stream, $offset, $next - $offset).' endobj';

        return $pdfDocument->parseObjectDefinitionString($objectDefStr, $oid);
    }

    public function attachObjectStreamIfPresent(PdfDocument $pdfDocument, PDFObject $object, int $offsetEnd, int $oid): void
    {
        $buffer = (string) $pdfDocument->getBuffer();
        $streamOffset = $this->resolveStreamStartOffset($buffer, $offsetEnd);
        if ($streamOffset === null) {
            return;
        }

        $lengthField = $object['Length'] ?? null;
        if ($lengthField === null) {
            throw new PdfCoreStructureException('Could not resolve stream length for object '.$oid.'.');
        }

        $length = $lengthField->asIntOrNull();
        if ($length === null) {
            $lengthObjectId = $lengthField->asObjectReferenceOrNull();
            if ($lengthObjectId === null || is_array($lengthObjectId)) {
                throw new PdfCoreStructureException('Could not resolve stream length reference for object '.$oid.'.');
            }

            $lengthObject = $pdfDocument->findObject($lengthObjectId);
            if ($lengthObject === null) {
                throw new PdfCoreStructureException('Could not resolve stream length object '.$lengthObjectId.' for object '.$oid.'.');
            }

            $length = $this->resolveLengthFromObject($pdfDocument, $lengthObject, [$lengthObjectId => true]);
        }

        if ($length === null || $length < 0) {
            throw new PdfCoreStructureException('Could not resolve valid stream length for object '.$oid.'.');
        }

        $object->setStream(substr($buffer, $streamOffset, $length));
    }

    /**
     * ISO 32000-1:2008 7.3.10: Resolve indirect /Length reference chain.
     * Handles cases where /Length -> obj N -> intValue or /Length -> obj N -> obj M -> intValue.
     *
     * @param  array<int, bool>  $visitedObjectIds  cycle detection
     */
    private function resolveLengthFromObject(PdfDocument $pdfDocument, PDFObject $lengthObject, array $visitedObjectIds): ?int
    {
        $embeddedScalar = $this->extractEmbeddedScalarValue($lengthObject);
        if ($embeddedScalar === null) {
            return null;
        }

        $embeddedLength = $embeddedScalar->asIntOrNull();
        if ($embeddedLength !== null) {
            return $embeddedLength;
        }

        $nextObjectId = $embeddedScalar->asObjectReferenceOrNull();
        if (! is_int($nextObjectId) || isset($visitedObjectIds[$nextObjectId])) {
            return null;
        }

        $nextObject = $pdfDocument->findObject($nextObjectId);
        if ($nextObject === null) {
            return null;
        }

        $visitedObjectIds[$nextObjectId] = true;

        return $this->resolveLengthFromObject($pdfDocument, $nextObject, $visitedObjectIds);
    }

    /**
     * Extract scalar value from object containing only a single embedded value.
     * Common in PDF length objects: "N 0 obj\n1234\nendobj" or "N 0 obj\n1 0 R\nendobj".
     */
    private function extractEmbeddedScalarValue(PDFObject $object): ?PDFValue
    {
        $keys = $object->getKeys();
        if (count($keys) !== 1) {
            return null;
        }

        $firstKey = $keys[0] ?? null;
        if ($firstKey === null) {
            return null;
        }

        $value = $object[$firstKey] ?? null;

        return $value instanceof PDFValue ? $value : null;
    }

    private function resolveStreamStartOffset(string $buffer, int $offsetEnd): ?int
    {
        if (substr($buffer, $offsetEnd - 7, 7) === "stream\n") {
            return $offsetEnd;
        }

        if (substr($buffer, $offsetEnd - 7, 8) === "stream\r\n") {
            return $offsetEnd + 1;
        }

        return null;
    }
}

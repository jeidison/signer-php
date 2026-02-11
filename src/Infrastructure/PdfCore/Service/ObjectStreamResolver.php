<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Service;

use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;

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

        $stream = $objstm->getStream(false);
        $index = substr((string) $stream, 0, $firstValue);
        $index = explode(' ', trim($index));
        $stream = substr((string) $stream, $firstValue);

        if (count($index) % 2 !== 0) {
            throw new PdfCoreParsingException('Invalid index for object stream '.$objstmOid);
        }

        $objpos *= 2;
        if ($objpos < 0 || ($objpos + 1) >= count($index)) {
            throw new PdfCoreStructureException(sprintf('Object %s not found in object stream %s.', $oid, $objstmOid));
        }

        $offset = (int) $index[$objpos + 1];
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

            $length = $lengthObject->getValue()->asIntOrNull();
        }

        if ($length === null || $length < 0) {
            throw new PdfCoreStructureException('Could not resolve valid stream length for object '.$oid.'.');
        }

        $object->setStream(substr($buffer, $streamOffset, $length));
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

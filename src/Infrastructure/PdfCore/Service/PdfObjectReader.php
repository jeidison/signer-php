<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Service;

use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
use SignerPHP\Infrastructure\PdfCore\ObjectParser;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\StreamReader;

final class PdfObjectReader
{
    public function objectFromBuffer(string $buffer, int|string|null $expectedObjId, int $offset = 0, int &$offsetEnd = 0): PDFObject
    {
        $bufferLength = strlen($buffer);
        if ($offset < 0 || $offset >= $bufferLength) {
            if (($expectedObjId !== null) && ((int) $expectedObjId > 0)) {
                $fallbackOffset = $this->findExpectedObjectHeaderOffset($buffer, (int) $expectedObjId, -1);
                if ($fallbackOffset !== null) {
                    return $this->objectFromBuffer($buffer, $expectedObjId, $fallbackOffset, $offsetEnd);
                }
            }

            throw new PdfCoreParsingException('Invalid object definition: '.$expectedObjId);
        }

        if (preg_match('/(\d+)\s+(\d+)\s+obj/ms', $buffer, $matches, PREG_OFFSET_CAPTURE, $offset) !== 1) {
            throw new PdfCoreParsingException('Invalid object definition: '.$expectedObjId);
        }

        $foundObjHeader = $matches[0][0];
        $foundObjHeaderOffset = $matches[0][1];
        $foundObjId = (int) $matches[1][0];
        $foundObjGeneration = (int) $matches[2][0];

        if ($expectedObjId === null) {
            $expectedObjId = $foundObjId;
        }

        if ($foundObjId !== $expectedObjId) {
            $fallbackOffset = $this->findExpectedObjectHeaderOffset($buffer, (int) $expectedObjId, $offset);
            if ($fallbackOffset !== null) {
                return $this->objectFromBuffer($buffer, $expectedObjId, $fallbackOffset, $offsetEnd);
            }

            throw new PdfCoreParsingException(sprintf(
                'PDF structure is corrupt: found obj %d while searching for obj %s (at %s).',
                $foundObjId,
                $expectedObjId,
                $offset
            ));
        }

        $offset = $foundObjHeaderOffset + strlen($foundObjHeader);

        $parser = new ObjectParser;
        $stream = new StreamReader($buffer, $offset);
        $objParsed = $parser->parse($stream);

        // Skip any trailing comments or stray tokens between >> and stream/endobj.
        $skipLimit = 32;
        while ($skipLimit-- > 0) {
            $tok = $parser->currentToken();
            if ($tok === ObjectParser::T_STREAM_BEGIN || $tok === ObjectParser::T_OBJECT_END) {
                break;
            }
            if ($tok === ObjectParser::T_OBJECT_BEGIN) {
                // Next object's header started without an `endobj` — treat as implicit endobj.
                break;
            }
            if ($tok === ObjectParser::T_COMMENT
                || $tok === ObjectParser::T_LIST_START
                || $tok === ObjectParser::T_LIST_END
                || $tok === ObjectParser::T_SIMPLE
                || $tok === ObjectParser::T_FIELD) {
                $parser->advanceToken();

                continue;
            }

            throw new PdfCoreParsingException('Malformed object');
        }

        $offsetEnd = $stream->getPosition();

        return new PDFObject($foundObjId, $objParsed, $foundObjGeneration);
    }

    private function findExpectedObjectHeaderOffset(string $buffer, int $expectedObjId, int $offset): ?int
    {
        if ($expectedObjId <= 0) {
            return null;
        }

        $bufferLength = strlen($buffer);
        $windowStart = max(0, $offset - 1048576);
        $windowEnd = min($bufferLength, max($offset + 1048576, 0));
        if ($windowEnd <= $windowStart) {
            $windowStart = 0;
            $windowEnd = $bufferLength;
        }

        $scanStart = $windowStart;
        $scanWindowLength = $windowEnd - $windowStart;
        if ($scanWindowLength === 0) {
            return null;
        }

        $window = substr($buffer, $scanStart, $scanWindowLength);
        $pattern = '/(?:^|[\r\n\x00\x09\x0C\x20])'.preg_quote((string) $expectedObjId, '/').'\s+\d+\s+obj\b/';
        if (preg_match_all($pattern, $window, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $bestOffset = null;
        $bestDistance = PHP_INT_MAX;

        foreach (($matches[0] ?? []) as $match) {
            $relativeOffset = (int) ($match[1] ?? -1);
            if ($relativeOffset < 0) {
                continue;
            }

            $header = (string) ($match[0] ?? '');
            $leadingWhitespace = strlen($header) - strlen(ltrim($header, "\r\n\x00\x09\x0C\x20"));
            $candidate = $scanStart + $relativeOffset + $leadingWhitespace;
            $distance = abs($candidate - $offset);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestOffset = $candidate;
            }
        }

        if (! is_int($bestOffset)) {
            return null;
        }

        return $bestOffset;
    }

    public function parseObjectDefinitionString(string $objectDefinition, int $expectedOid): PDFObject
    {
        if (preg_match('/(\d+)\s+(\d+)\s+obj/ms', $objectDefinition, $matches) !== 1) {
            throw new PdfCoreParsingException('Object stream entry is not a valid PDF object definition.');
        }

        $foundObjId = (int) $matches[1];
        $foundObjGeneration = (int) $matches[2];
        if ($foundObjId !== $expectedOid) {
            throw new PdfCoreParsingException(sprintf(
                'Object stream is corrupt: found obj %d while expecting obj %d.',
                $foundObjId,
                $expectedOid
            ));
        }

        $offset = strlen($matches[0]);
        $parser = new ObjectParser;
        $stream = new StreamReader($objectDefinition, $offset);
        $objParsed = $parser->parse($stream);

        return new PDFObject($foundObjId, $objParsed, $foundObjGeneration);
    }
}

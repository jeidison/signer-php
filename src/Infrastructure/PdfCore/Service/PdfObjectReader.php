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
        $headerMatchedAt = strpos($buffer, 'obj', $offset);
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

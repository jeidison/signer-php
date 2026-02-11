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
        if (preg_match('/(\d+)\s+(\d+)\s+obj/ms', $buffer, $matches, 0, $offset) !== 1) {
            throw new PdfCoreParsingException('Invalid object definition: '.$expectedObjId);
        }

        $foundObjHeader = $matches[0];
        $foundObjId = (int) $matches[1];
        $foundObjGeneration = (int) $matches[2];

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

        $offset += strlen($foundObjHeader);

        $parser = new ObjectParser;
        $stream = new StreamReader($buffer, $offset);
        $objParsed = $parser->parse($stream);

        switch ($parser->currentToken()) {
            case ObjectParser::T_STREAM_BEGIN:
            case ObjectParser::T_OBJECT_END:
                break;
            default:
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

<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Parsing;

use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
use SignerPHP\Infrastructure\PdfCore\ObjectParser;
use SignerPHP\Infrastructure\PdfCore\StreamReader;

final class ObjectLexer
{
    private ?StreamReader $buffer = null;

    private string|false $currentChar = false;

    private string|false $nextChar = false;

    public function start(StreamReader $buffer): void
    {
        $this->buffer = $buffer;
        $this->currentChar = false;
        $this->nextChar = false;

        if ($this->buffer->size() === 0) {
            return;
        }

        $this->nextChar = $this->buffer->currentChar();
        $this->advanceChar();
    }

    /**
     * @return array{0:string|false,1:int|false}
     */
    public function nextToken(): array
    {
        if ($this->currentChar === false) {
            return [false, false];
        }

        $this->skipWhitespace();
        if ($this->currentChar === false) {
            return [false, false];
        }

        $token = $this->readStructuralToken();
        if ($token !== null) {
            return $token;
        }

        return $this->readSimpleToken();
    }

    public function debugSuffix(): string
    {
        $position = $this->buffer?->getPosition() ?? 0;
        $preview = $this->buffer?->subStrAtPos(50) ?? '';

        return sprintf('pos: %d, c: %s, n: %s, b: %s', $position, (string) $this->currentChar, (string) $this->nextChar, $preview);
    }

    private function advanceChar(): string|false
    {
        if ($this->buffer === null) {
            return false;
        }

        $this->currentChar = $this->nextChar;
        $this->nextChar = $this->buffer->nextChar();

        return $this->currentChar;
    }

    private function isSeparator(): bool
    {
        $doubleSeparators = ['<<', '>>'];

        return ($this->currentChar === false)
            || str_contains("%<>()[]{}/ \n\r\t", (string) $this->currentChar)
            || in_array($this->currentChar.$this->nextChar, $doubleSeparators, true);
    }

    private function parseHexString(): string
    {
        $token = '';

        if ($this->currentChar !== '<') {
            throw new PdfCoreParsingException('Invalid hex string');
        }

        $this->advanceChar();
        while (($this->currentChar !== '>') && (str_contains('0123456789abcdefABCDEF \t\r\n\f', (string) $this->currentChar))) {
            $token .= $this->currentChar;
            if ($this->advanceChar() === false) {
                break;
            }
        }

        if (($this->currentChar !== false) && (! str_contains('>0123456789abcdefABCDEF \t\r\n\f', (string) $this->currentChar))) {
            throw new PdfCoreParsingException('Invalid hex string');
        }

        if ($this->currentChar !== '>') {
            throw new PdfCoreParsingException('Invalid hex string');
        }

        $this->advanceChar();

        return $token;
    }

    private function parseString(): string
    {
        $token = '';

        if ($this->currentChar !== '(') {
            throw new PdfCoreParsingException('Invalid string');
        }

        $parenthesisCount = 1;
        while ($this->currentChar !== false) {
            $this->advanceChar();

            if (($this->currentChar === ')') && (! strlen($token) || ($token[strlen($token) - 1] !== '\\'))) {
                $parenthesisCount--;
                if ($parenthesisCount === 0) {
                    break;
                }
            } else {
                if (($this->currentChar === '(') && (! strlen($token) || ($token[strlen($token) - 1] !== '\\'))) {
                    $parenthesisCount++;
                }

                $token .= $this->currentChar;
            }
        }

        if ($this->currentChar !== ')') {
            throw new PdfCoreParsingException('Invalid string');
        }

        $this->advanceChar();

        return $token;
    }

    /**
     * @return array{0:string,1:int}|null
     */
    private function readStructuralToken(): ?array
    {
        return match ($this->currentChar) {
            '%' => [$this->readComment(), ObjectParser::T_COMMENT],
            '<' => $this->nextChar === '<'
                ? [$this->readDoubleCharDelimiter('<<'), ObjectParser::T_DICT_START]
                : [$this->parseHexString(), ObjectParser::T_HEX_STRING],
            '(' => [$this->parseString(), ObjectParser::T_STRING],
            '>' => $this->nextChar === '>'
                ? [$this->readDoubleCharDelimiter('>>'), ObjectParser::T_DICT_END]
                : null,
            '[' => [$this->readSingleCharDelimiter(), ObjectParser::T_LIST_START],
            ']' => [$this->readSingleCharDelimiter(), ObjectParser::T_LIST_END],
            '/' => [$this->readFieldName(), ObjectParser::T_FIELD],
            default => null,
        };
    }

    /**
     * @return array{0:string,1:int}
     */
    private function readSimpleToken(): array
    {
        $token = '';
        while (! $this->isSeparator()) {
            $token .= $this->currentChar;
            if ($this->advanceChar() === false) {
                break;
            }
        }

        $type = match ($token) {
            'obj' => ObjectParser::T_OBJECT_BEGIN,
            'endobj' => ObjectParser::T_OBJECT_END,
            'stream' => ObjectParser::T_STREAM_BEGIN,
            'endstream' => ObjectParser::T_STREAM_END,
            default => ObjectParser::T_SIMPLE,
        };

        return [$token, $type];
    }

    private function readComment(): string
    {
        $this->advanceChar();
        $token = '';
        while (($this->currentChar !== false) && (! str_contains("\n\r", (string) $this->currentChar))) {
            $token .= $this->currentChar;
            $this->advanceChar();
        }

        return $token;
    }

    private function readFieldName(): string
    {
        $this->advanceChar();
        $field = '';
        while (! $this->isSeparator()) {
            $field .= $this->currentChar;
            if ($this->advanceChar() === false) {
                break;
            }
        }

        return $field;
    }

    private function readSingleCharDelimiter(): string
    {
        $token = (string) $this->currentChar;
        $this->advanceChar();

        return $token;
    }

    private function readDoubleCharDelimiter(string $delimiter): string
    {
        $this->advanceChar();
        $this->advanceChar();

        return $delimiter;
    }

    private function skipWhitespace(): void
    {
        while ((str_contains("\t\n\r ", (string) $this->currentChar)) && ($this->advanceChar() !== false));
    }
}

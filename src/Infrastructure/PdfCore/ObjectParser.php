<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore;

use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
use SignerPHP\Infrastructure\PdfCore\Parsing\ObjectLexer;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValue;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueHexString;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueList;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueString;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueType;
use Stringable;

class ObjectParser implements Stringable
{
    final public const T_NOTOKEN = 0;

    final public const T_LIST_START = 1;

    final public const T_LIST_END = 2;

    final public const T_FIELD = 3;

    final public const T_STRING = 4;

    final public const T_HEX_STRING = 12;

    final public const T_SIMPLE = 5;

    final public const T_DICT_START = 6;

    final public const T_DICT_END = 7;

    final public const T_OBJECT_BEGIN = 8;

    final public const T_OBJECT_END = 9;

    final public const T_STREAM_BEGIN = 10;

    final public const T_STREAM_END = 11;

    final public const T_COMMENT = 13;

    final public const T_NAMES = [
        self::T_NOTOKEN => 'no token',
        self::T_LIST_START => 'list start',
        self::T_LIST_END => 'list end',
        self::T_FIELD => 'field',
        self::T_STRING => 'string',
        self::T_HEX_STRING => 'hex string',
        self::T_SIMPLE => 'simple',
        self::T_DICT_START => 'dict start',
        self::T_DICT_END => 'dict end',
        self::T_OBJECT_BEGIN => 'object begin',
        self::T_OBJECT_END => 'object end',
        self::T_STREAM_BEGIN => 'stream begin',
        self::T_STREAM_END => 'stream end',
        self::T_COMMENT => 'comment',
    ];

    protected string|false $currentTokenValue = false;

    protected int|false $currentTokenType = self::T_NOTOKEN;

    private ObjectLexer $lexer;

    public function __construct(?ObjectLexer $lexer = null)
    {
        $this->lexer = $lexer ?? new ObjectLexer;
    }

    public function currentToken(): int|false
    {
        return $this->currentTokenType;
    }

    public function parse(StreamReader $stream): ?PDFValue
    {
        $this->lexer->start($stream);
        $this->advanceToken();
        if ($this->currentTokenValue === false) {
            throw new PdfCoreParsingException('Empty or invalid object content');
        }

        return $this->parseValue();
    }

    public function parseString(string $str, int $offset = 0): ?PDFValue
    {
        $stream = new StreamReader($str);
        $stream->goto($offset);

        return $this->parse($stream);
    }

    public function __toString(): string
    {
        return sprintf(
            't: %s, tt: %s, %s',
            (string) $this->currentTokenValue,
            self::T_NAMES[$this->currentTokenType] ?? 'unknown',
            $this->lexer->debugSuffix(),
        )."\n";
    }

    public function advanceToken(): string|false
    {
        [$this->currentTokenValue, $this->currentTokenType] = $this->lexer->nextToken();

        return $this->currentTokenValue;
    }

    private function parseObject(): PDFValueObject
    {
        if ($this->currentTokenType !== self::T_DICT_START) {
            throw new PdfCoreParsingException('Invalid object definition');
        }

        $this->advanceToken();
        $object = [];

        while ($this->currentTokenValue !== false) {
            switch ($this->currentTokenType) {
                case self::T_FIELD:
                    $field = $this->currentTokenValue;
                    $this->advanceToken();
                    $value = $this->parseValue();
                    if ($value === null) {
                        throw new PdfCoreParsingException('Invalid null object value for field '.$field);
                    }
                    $object[$field] = $value;
                    break;

                case self::T_DICT_END:
                    $this->advanceToken();

                    return new PDFValueObject($object);

                default:
                    throw new PdfCoreParsingException('Invalid token: '.$this);
            }
        }

        throw new PdfCoreParsingException('Unexpected end of object definition');
    }

    private function parseList(): PDFValueList
    {
        if ($this->currentTokenType !== self::T_LIST_START) {
            throw new PdfCoreParsingException('Invalid list definition');
        }

        $this->advanceToken();
        $list = [];

        while ($this->currentTokenValue !== false) {
            switch ($this->currentTokenType) {
                case self::T_LIST_END:
                    $this->advanceToken();

                    return new PDFValueList($list);

                case self::T_OBJECT_BEGIN:
                case self::T_OBJECT_END:
                case self::T_STREAM_BEGIN:
                case self::T_STREAM_END:
                    throw new PdfCoreParsingException('Invalid list definition');
                default:
                    $value = $this->parseValue();
                    if ($value !== null) {
                        $list[] = $value;
                    }
            }
        }

        throw new PdfCoreParsingException('Unexpected end of list definition');
    }

    private function parseValue(): ?PDFValue
    {
        while ($this->currentTokenValue !== false) {
            switch ($this->currentTokenType) {
                case self::T_DICT_START:
                    return $this->parseObject();

                case self::T_LIST_START:
                    return $this->parseList();

                case self::T_STRING:
                    return $this->consumeLiteral(new PDFValueString($this->currentTokenValue));

                case self::T_HEX_STRING:
                    return $this->consumeLiteral(new PDFValueHexString($this->currentTokenValue));

                case self::T_FIELD:
                    return $this->consumeLiteral(new PDFValueType($this->currentTokenValue));

                case self::T_OBJECT_BEGIN:
                case self::T_STREAM_END:
                    throw new PdfCoreParsingException('Invalid keyword');
                case self::T_OBJECT_END:
                case self::T_STREAM_BEGIN:
                    return null;

                case self::T_COMMENT:
                    $this->advanceToken();
                    break;

                case self::T_SIMPLE:
                    return $this->parseSimpleValue();

                default:
                    throw new PdfCoreParsingException('Invalid token: '.$this);
            }
        }

        throw new PdfCoreParsingException('Unexpected end while parsing value');
    }

    private function consumeLiteral(PDFValue $value): PDFValue
    {
        $this->advanceToken();

        return $value;
    }

    private function parseSimpleValue(): PDFValueSimple
    {
        $simpleValue = $this->currentTokenValue;
        $this->advanceToken();

        while ($this->currentTokenValue !== false && $this->currentTokenType === self::T_SIMPLE) {
            $simpleValue .= ' '.$this->currentTokenValue;
            $this->advanceToken();
        }

        return new PDFValueSimple($simpleValue);
    }
}

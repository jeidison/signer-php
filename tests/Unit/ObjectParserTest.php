<?php

declare(strict_types=1);

namespace PdfSigner\Tests\Unit;

use Exception;
use PdfSigner\Infrastructure\PdfCore\ObjectParser;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueHexString;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueList;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueString;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueType;
use PHPUnit\Framework\TestCase;

final class ObjectParserTest extends TestCase
{
    public function test_parser_builds_pdf_value_object_tree(): void
    {
        $parser = new ObjectParser;

        $parsed = $parser->parseString('<< /Type /Sig /Name (John) /Flags [1 2 3] /Data <4142> >>');

        self::assertInstanceOf(PDFValueObject::class, $parsed);
        self::assertInstanceOf(PDFValueType::class, $parsed['Type']);
        self::assertInstanceOf(PDFValueString::class, $parsed['Name']);
        self::assertInstanceOf(PDFValueList::class, $parsed['Flags']);
        self::assertInstanceOf(PDFValueHexString::class, $parsed['Data']);
        self::assertSame('Sig', $parsed['Type']->val());
        self::assertSame('John', $parsed['Name']->val());
    }

    public function test_parser_throws_on_empty_input(): void
    {
        $parser = new ObjectParser;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Empty or invalid object content');

        $parser->parseString('');
    }

    public function test_parser_throws_on_unexpected_end_of_list(): void
    {
        $parser = new ObjectParser;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unexpected end of list definition');

        $parser->parseString('[1 2 3');
    }

    public function test_parser_parses_simple_sequence_as_single_value(): void
    {
        $parser = new ObjectParser;
        $parsed = $parser->parseString('10 0 R');

        self::assertNotNull($parsed);
        self::assertSame('10 0 R', (string) $parsed);
    }

    public function test_parser_returns_null_for_endobj_and_stream_keyword_boundaries(): void
    {
        $parser = new ObjectParser;

        self::assertNull($parser->parseString('endobj'));
        self::assertNull($parser->parseString('stream'));
    }

    public function test_parser_throws_for_invalid_keyword_token(): void
    {
        $parser = new ObjectParser;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid keyword');

        $parser->parseString('obj');
    }

    public function test_parser_throws_for_invalid_dict_value_after_field(): void
    {
        $parser = new ObjectParser;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid null object value for field Type');

        $parser->parseString('<< /Type stream >>');
    }

    public function test_parser_throws_for_invalid_token_inside_dictionary(): void
    {
        $parser = new ObjectParser;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid token:');

        $parser->parseString('<< 1 >>');
    }

    public function test_parser_throws_for_invalid_keyword_inside_list(): void
    {
        $parser = new ObjectParser;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid list definition');

        $parser->parseString('[obj]');
    }

    public function test_parser_throws_when_only_comment_is_present(): void
    {
        $parser = new ObjectParser;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unexpected end while parsing value');

        $parser->parseString("% comment\n");
    }

    public function test_parser_throws_on_unexpected_end_of_object_definition(): void
    {
        $parser = new ObjectParser;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unexpected end of object definition');

        $parser->parseString('<< /Type /Catalog');
    }

    public function test_private_parse_object_and_list_guards_and_default_token_path(): void
    {
        $parser = new ObjectParser;
        $this->setParserTokenState($parser, 'x', ObjectParser::T_SIMPLE);

        $parseObject = new \ReflectionMethod($parser, 'parseObject');
        $parseObject->setAccessible(true);
        try {
            $parseObject->invoke($parser);
            self::fail('Expected exception not thrown for parseObject guard.');
        } catch (\ReflectionException|\Throwable $e) {
            self::assertStringContainsString('Invalid object definition', $e->getMessage());
        }

        $parseList = new \ReflectionMethod($parser, 'parseList');
        $parseList->setAccessible(true);
        try {
            $parseList->invoke($parser);
            self::fail('Expected exception not thrown for parseList guard.');
        } catch (\ReflectionException|\Throwable $e) {
            self::assertStringContainsString('Invalid list definition', $e->getMessage());
        }

        $this->setParserTokenState($parser, 'x', 999);
        $parseValue = new \ReflectionMethod($parser, 'parseValue');
        $parseValue->setAccessible(true);
        try {
            $parseValue->invoke($parser);
            self::fail('Expected exception not thrown for parseValue default token.');
        } catch (\ReflectionException|\Throwable $e) {
            self::assertStringContainsString('Invalid token:', $e->getMessage());
        }
    }

    private function setParserTokenState(ObjectParser $parser, string|false $value, int|false $type): void
    {
        $valueProp = new \ReflectionProperty($parser, 'currentTokenValue');
        $valueProp->setAccessible(true);
        $valueProp->setValue($parser, $value);

        $typeProp = new \ReflectionProperty($parser, 'currentTokenType');
        $typeProp->setAccessible(true);
        $typeProp->setValue($parser, $type);
    }
}

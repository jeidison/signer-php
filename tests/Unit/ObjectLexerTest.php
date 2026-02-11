<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\ObjectParser;
use SignerPHP\Infrastructure\PdfCore\Parsing\ObjectLexer;
use SignerPHP\Infrastructure\PdfCore\StreamReader;

final class ObjectLexerTest extends TestCase
{
    public function test_lexer_tokenizes_dictionary_and_hex_string(): void
    {
        $lexer = new ObjectLexer;
        $lexer->start(new StreamReader('<< /Type /Sig /Name (John) /Data <4142> >>'));

        $tokens = [];
        do {
            [$value, $type] = $lexer->nextToken();
            if ($value === false) {
                break;
            }

            $tokens[] = [$value, $type];
        } while (true);

        self::assertSame(['<<', ObjectParser::T_DICT_START], $tokens[0]);
        self::assertSame(['>>', ObjectParser::T_DICT_END], $tokens[array_key_last($tokens)]);

        self::assertContains(['Type', ObjectParser::T_FIELD], $tokens);
        self::assertContains(['Sig', ObjectParser::T_FIELD], $tokens);
        self::assertContains(['John', ObjectParser::T_STRING], $tokens);
        self::assertContains(['4142', ObjectParser::T_HEX_STRING], $tokens);
    }

    public function test_lexer_parses_comment_until_end_of_buffer(): void
    {
        $lexer = new ObjectLexer;
        $lexer->start(new StreamReader('% comment without newline'));

        [$value, $type] = $lexer->nextToken();

        self::assertSame(' comment without newline', $value);
        self::assertSame(ObjectParser::T_COMMENT, $type);
        self::assertSame([false, false], $lexer->nextToken());
    }

    public function test_lexer_parses_simple_keywords_and_debug_suffix(): void
    {
        $lexer = new ObjectLexer;
        $lexer->start(new StreamReader('obj endobj stream endstream name'));

        self::assertSame(['obj', ObjectParser::T_OBJECT_BEGIN], $lexer->nextToken());
        self::assertSame(['endobj', ObjectParser::T_OBJECT_END], $lexer->nextToken());
        self::assertSame(['stream', ObjectParser::T_STREAM_BEGIN], $lexer->nextToken());
        self::assertSame(['endstream', ObjectParser::T_STREAM_END], $lexer->nextToken());
        self::assertSame(['name', ObjectParser::T_SIMPLE], $lexer->nextToken());
        self::assertStringContainsString('pos:', $lexer->debugSuffix());
    }

    public function test_private_parsers_throw_for_invalid_hex_and_string_input(): void
    {
        $lexer = new ObjectLexer;
        $lexer->start(new StreamReader('x'));

        $this->setLexerProperty($lexer, 'currentChar', 'x');

        $parseHex = new \ReflectionMethod($lexer, 'parseHexString');
        $parseHex->setAccessible(true);
        try {
            $parseHex->invoke($lexer);
            self::fail('Expected parseHexString exception not thrown.');
        } catch (\Throwable $e) {
            self::assertStringContainsString('Invalid hex string', $e->getMessage());
        }

        $parseString = new \ReflectionMethod($lexer, 'parseString');
        $parseString->setAccessible(true);
        try {
            $parseString->invoke($lexer);
            self::fail('Expected parseString exception not thrown.');
        } catch (\Throwable $e) {
            self::assertStringContainsString('Invalid string', $e->getMessage());
        }
    }

    public function test_next_token_and_debug_suffix_without_start_are_safe(): void
    {
        $lexer = new ObjectLexer;

        self::assertSame([false, false], $lexer->nextToken());
        self::assertStringContainsString('pos: 0', $lexer->debugSuffix());
    }

    public function test_start_with_empty_buffer_has_no_tokens(): void
    {
        $lexer = new ObjectLexer;
        $lexer->start(new StreamReader(''));

        self::assertSame([false, false], $lexer->nextToken());
    }

    public function test_hex_parser_rejects_invalid_character_and_missing_closer(): void
    {
        $lexer = new ObjectLexer;
        $lexer->start(new StreamReader('<41Z>'));

        $this->expectExceptionMessage('Invalid hex string');
        $lexer->nextToken();
    }

    public function test_hex_parser_rejects_unclosed_hex_string(): void
    {
        $lexer = new ObjectLexer;
        $lexer->start(new StreamReader('<41'));

        $this->expectExceptionMessage('Invalid hex string');
        $lexer->nextToken();
    }

    public function test_parse_string_with_nested_parenthesis_and_invalid_unclosed_string(): void
    {
        $lexer = new ObjectLexer;
        $lexer->start(new StreamReader('(a(b)c)'));
        [$value, $type] = $lexer->nextToken();
        self::assertSame(ObjectParser::T_STRING, $type);
        self::assertStringContainsString('a(b', (string) $value);

        $lexer = new ObjectLexer;
        $lexer->start(new StreamReader('(abc'));
        $this->expectExceptionMessage('Invalid string');
        $lexer->nextToken();
    }

    public function test_advance_char_returns_false_when_buffer_is_not_initialized(): void
    {
        $lexer = new ObjectLexer;
        $advance = new \ReflectionMethod($lexer, 'advanceChar');
        $advance->setAccessible(true);

        self::assertFalse($advance->invoke($lexer));
    }

    private function setLexerProperty(ObjectLexer $lexer, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($lexer, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($lexer, $value);
    }
}
